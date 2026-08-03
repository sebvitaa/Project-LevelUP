<?php

namespace App\Services\Ai;

use App\Enums\ProjectStatus;
use App\Exceptions\PlanGenerationException;
use App\Models\Project;
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
     * @throws PlanGenerationException
     */
    public function generate(Project $project): void
    {
        $payload = $this->client->generateJson(
            $this->prompts->systemInstruction($project),
            $this->prompts->userPrompt($project),
            $this->prompts->responseSchema(),
        );

        $activities = $this->validatePlan($payload);

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

        $this->persist($project, $activities, $schedule);
    }

    /**
     * Valida la forma de la respuesta antes de tocar la base de datos.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{code: string, name: string, description: string, duration_days: int, predecessors: array<int, string>}>
     *
     * @throws PlanGenerationException
     */
    private function validatePlan(array $payload): array
    {
        $activities = $payload['activities'] ?? null;

        if (! is_array($activities) || $activities === []) {
            throw PlanGenerationException::invalidResponse('no vino ninguna actividad');
        }

        $clean = [];

        foreach ($activities as $activity) {
            if (! is_array($activity)) {
                throw PlanGenerationException::invalidResponse('una actividad no es un objeto');
            }

            foreach (['code', 'name', 'duration_days'] as $key) {
                if (! isset($activity[$key])) {
                    throw PlanGenerationException::invalidResponse("a una actividad le falta el campo [{$key}]");
                }
            }

            $duration = (int) $activity['duration_days'];

            if ($duration < 1) {
                throw PlanGenerationException::invalidResponse(
                    "la actividad [{$activity['code']}] tiene una duración inválida"
                );
            }

            $predecessors = $activity['predecessors'] ?? [];

            $clean[] = [
                'code' => (string) $activity['code'],
                'name' => (string) $activity['name'],
                'description' => (string) ($activity['description'] ?? ''),
                'duration_days' => $duration,
                'predecessors' => is_array($predecessors) ? array_map(strval(...), $predecessors) : [],
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
    private function persist(Project $project, array $activities, array $schedule): void
    {
        DB::transaction(function () use ($project, $activities, $schedule): void {
            $project->activities()->delete();

            $models = [];

            foreach ($activities as $activity) {
                $models[$activity['code']] = $project->activities()->forceCreate([
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

            $project->forceFill([
                'status' => ProjectStatus::Ready,
                'total_duration_days' => $this->cpm->totalDuration($schedule),
                'generation_error' => null,
                'generated_at' => now(),
            ])->save();
        });
    }
}
