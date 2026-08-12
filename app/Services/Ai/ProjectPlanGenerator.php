<?php

namespace App\Services\Ai;

use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Exceptions\PlanGenerationException;
use App\Models\Project;
use App\Models\User;
use App\Services\Cpm\CpmCalculator;
use App\Services\Cpm\ScheduledActivity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Orquesta el paso de "prompt del usuario" a "malla CPM persistida".
 *
 * Secuencia: pedir el plan a Gemini → validar la forma de la respuesta →
 * resolver el CPM en el servidor → guardar todo en una transacción.
 */
class ProjectPlanGenerator
{
    public function __construct(
        private readonly GeminiClient $client,
        private readonly PromptBuilder $prompts,
        private readonly CpmCalculator $cpm,
    ) {}

    /**
     * @return bool Whether the plan was persisted for the current attempt.
     *
     * @throws PlanGenerationException
     */
    public function generate(Project $project, int $generationAttempt): bool
    {
        $project->load([
            'clarifications' => fn ($query) => $query
                ->where('generation_attempt', $generationAttempt)
                ->whereNotNull('answered_at'),
        ]);

        $payload = $this->client->generateJson(
            $this->prompts->systemInstruction($project),
            $this->prompts->userPrompt($project, $generationAttempt),
            $this->prompts->responseSchema(),
        );

        $activities = $this->validatePlan($payload, $project);

        try {
            $schedule = $this->cpm->calculate(array_map(
                fn (array $activity): array => [
                    'code' => $activity['code'],
                    'duration_days' => $activity['duration_days'],
                    'predecessors' => $activity['predecessors'],
                ],
                $activities
            ));
        } catch (InvalidArgumentException $e) {
            throw PlanGenerationException::invalidGraph($e->getMessage());
        }

        return $this->persist($project, $activities, $schedule, $generationAttempt);
    }

    /**
     * Valida la forma de la respuesta antes de tocar la base de datos.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{code: string, name: string, description: string, duration_days: int, predecessors: array<int, string>}>
     *
     * @throws PlanGenerationException
     */
    private function validatePlan(array $payload, Project $project): array
    {
        $activities = $payload['activities'] ?? null;

        if (! is_array($activities) || $activities === []) {
            throw PlanGenerationException::invalidResponse('no vino ninguna actividad');
        }

        [$minimum, $maximum] = $project->type->activityRange();

        if (count($activities) < $minimum || count($activities) > $maximum) {
            throw PlanGenerationException::invalidResponse(
                "la malla debe tener entre {$minimum} y {$maximum} actividades"
            );
        }

        $clean = [];
        $codes = [];

        foreach ($activities as $activity) {
            if (! is_array($activity)) {
                throw PlanGenerationException::invalidResponse('una actividad no es un objeto');
            }

            $code = $activity['code'] ?? null;
            $name = $activity['name'] ?? null;
            $description = $activity['description'] ?? '';
            $duration = $activity['duration_days'] ?? null;
            $predecessors = $activity['predecessors'] ?? [];

            if (! is_string($code) || ! preg_match('/^[A-Z][A-Z0-9]{0,3}$/', $code) || isset($codes[$code])) {
                throw PlanGenerationException::invalidResponse('hay un código inválido o repetido');
            }

            if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 255) {
                throw PlanGenerationException::invalidResponse('hay un nombre vacío o demasiado largo');
            }

            if (! is_string($description) || mb_strlen($description) > 10000) {
                throw PlanGenerationException::invalidResponse('hay una descripción demasiado larga');
            }

            if (! is_int($duration) || $duration < 1 || $duration > 65535) {
                throw PlanGenerationException::invalidResponse(
                    "la actividad [{$code}] tiene una duración inválida"
                );
            }

            if (! is_array($predecessors) || count(array_filter(
                $predecessors,
                static fn (mixed $predecessor): bool => ! is_string($predecessor)
            )) > 0) {
                throw PlanGenerationException::invalidResponse(
                    "la actividad [{$code}] tiene precedentes inválidos"
                );
            }

            $codes[$code] = true;
            $clean[] = [
                'code' => $code,
                'name' => trim($name),
                'description' => trim($description),
                'duration_days' => $duration,
                'predecessors' => array_values($predecessors),
            ];
        }

        return $clean;
    }

    /**
     * Guarda actividades, dependencias y resultados CPM en una transacción.
     *
     * Reemplaza la malla completa: regenerar un proyecto borra la anterior en
     * vez de intentar mezclarla, que daría un grafo inconsistente.
     *
     * @param  array<int, array{code: string, name: string, description: string, duration_days: int, predecessors: array<int, string>}>  $activities
     * @param  array<string, ScheduledActivity>  $schedule
     */
    private function persist(Project $project, array $activities, array $schedule, int $generationAttempt): bool
    {
        return DB::transaction(function () use ($project, $activities, $schedule, $generationAttempt): bool {
            $lockedProject = Project::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedProject === null || ! $lockedProject->isCurrentGenerationAttempt($generationAttempt)) {
                return false;
            }

            if ($lockedProject->charged_generation_attempt === $generationAttempt) {
                return false;
            }

            $lockedUser = User::query()
                ->whereKey($lockedProject->user_id)
                ->lockForUpdate()
                ->first();

            if ($lockedUser === null || ! $lockedUser->hasAiCreditsAvailable()) {
                throw PlanGenerationException::noCreditsLeft();
            }

            $lockedProject->activities()->delete();

            $models = [];

            foreach ($activities as $activity) {
                $models[$activity['code']] = $lockedProject->activities()->forceCreate([
                    'code' => $activity['code'],
                    'name' => $activity['name'],
                    'description' => $activity['description'],
                    'duration_days' => $activity['duration_days'],
                    ...$schedule[$activity['code']]->toAttributes(),
                ]);
            }

            foreach ($activities as $activity) {
                $predecessorIds = array_map(
                    fn (string $code): int => $models[$code]->getKey(),
                    $activity['predecessors']
                );

                $models[$activity['code']]->predecessors()->sync($predecessorIds);
            }

            $lockedProject->forceFill([
                'status' => ProjectStatus::Ready,
                'generation_stage' => ProjectGenerationStage::Complete,
                'charged_generation_attempt' => $generationAttempt,
                'total_duration_days' => $this->cpm->totalDuration($schedule),
                'generation_error' => null,
                'generated_at' => now(),
            ])->save();

            $lockedUser->consumeAiCredit();

            return true;
        });
    }
}
