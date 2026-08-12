<?php

namespace App\Services\Ai;

use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Exceptions\PlanGenerationException;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Analiza el brief y persiste una única ronda de preguntas, si hace falta.
 */
class ProjectClarificationGenerator
{
    public function __construct(
        private readonly GeminiClient $client,
        private readonly ClarificationPromptBuilder $prompts,
    ) {}

    /**
     * @return bool|null True when the project now awaits user input, false when it may generate the plan, null when stale.
     *
     * @throws PlanGenerationException
     */
    public function generate(Project $project, int $generationAttempt): ?bool
    {
        $payload = $this->client->generateJson(
            $this->prompts->systemInstruction($project),
            $this->prompts->userPrompt($project),
            $this->prompts->responseSchema(),
        );

        [$needsClarification, $questions] = $this->validateResponse($payload);

        return DB::transaction(function () use (
            $project,
            $generationAttempt,
            $needsClarification,
            $questions,
        ): ?bool {
            $lockedProject = Project::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedProject === null
                || $lockedProject->generation_attempt !== $generationAttempt
                || $lockedProject->status !== ProjectStatus::Clarifying) {
                return null;
            }

            if ($needsClarification) {
                $lockedProject->clarifications()
                    ->where('generation_attempt', $generationAttempt)
                    ->delete();

                $lockedProject->clarifications()->createMany(array_map(
                    fn (array $question): array => [
                        'round' => 1,
                        'generation_attempt' => $generationAttempt,
                        ...$question,
                    ],
                    $questions,
                ));

                $lockedProject->forceFill([
                    'status' => ProjectStatus::AwaitingInput,
                    'generation_stage' => ProjectGenerationStage::AwaitingAnswers,
                    'generation_progressed_at' => now(),
                    'generation_error' => null,
                ])->save();

                return true;
            }

            $lockedProject->forceFill([
                'status' => ProjectStatus::Generating,
                'generation_stage' => ProjectGenerationStage::RequestingPlan,
                'generation_progressed_at' => now(),
                'generation_error' => null,
            ])->save();

            return false;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: bool, 1: array<int, array{key: string, question: string, rationale: ?string, input_type: string, options: ?array<int, string>}>}
     *
     * @throws PlanGenerationException
     */
    private function validateResponse(array $payload): array
    {
        $needsClarification = $payload['needs_clarification'] ?? null;
        $questions = $payload['questions'] ?? null;

        if (! is_bool($needsClarification) || ! is_array($questions)) {
            throw PlanGenerationException::invalidClarificationResponse(
                'la decisión o la lista de preguntas tiene un formato inválido'
            );
        }

        $count = count($questions);

        if ((! $needsClarification && $count !== 0) || ($needsClarification && ($count < 1 || $count > 3))) {
            throw PlanGenerationException::invalidClarificationResponse(
                'la cantidad de preguntas no coincide con la decisión de aclarar'
            );
        }

        $clean = [];
        $keys = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                throw PlanGenerationException::invalidClarificationResponse('una pregunta no es un objeto');
            }

            $key = $question['key'] ?? null;
            $text = $question['question'] ?? null;
            $rationale = $question['rationale'] ?? null;
            $inputType = $question['input_type'] ?? null;
            $options = $question['options'] ?? [];

            if (! is_string($key) || ! preg_match('/^[a-z0-9_]{1,64}$/', $key) || isset($keys[$key])) {
                throw PlanGenerationException::invalidClarificationResponse('hay una key inválida o repetida');
            }

            if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 2000) {
                throw PlanGenerationException::invalidClarificationResponse('hay una pregunta vacía o demasiado larga');
            }

            if ($rationale !== null && (! is_string($rationale) || mb_strlen($rationale) > 2000)) {
                throw PlanGenerationException::invalidClarificationResponse('hay una justificación inválida');
            }

            if (! is_string($inputType) || ! in_array($inputType, ['text', 'select'], true)) {
                throw PlanGenerationException::invalidClarificationResponse('el tipo de entrada no está permitido');
            }

            if (! is_array($options)) {
                throw PlanGenerationException::invalidClarificationResponse('las opciones no son una lista');
            }

            $options = array_map(
                static fn (mixed $option): string => is_string($option) ? trim($option) : '',
                $options,
            );

            if ($inputType === 'text' && $options !== []) {
                throw PlanGenerationException::invalidClarificationResponse(
                    'una pregunta de texto no puede traer opciones'
                );
            }

            if ($inputType === 'select') {
                $normalizedOptions = array_map(mb_strtolower(...), $options);

                if (count($options) < 2
                    || count($options) > 8
                    || in_array('', $options, true)
                    || count(array_unique($normalizedOptions)) !== count($options)
                    || count(array_filter($options, fn (string $option): bool => mb_strlen($option) > 120)) > 0) {
                    throw PlanGenerationException::invalidClarificationResponse(
                        'las opciones deben ser entre 2 y 8 valores únicos y acotados'
                    );
                }
            }

            $keys[$key] = true;
            $clean[] = [
                'key' => $key,
                'question' => trim($text),
                'rationale' => $rationale === null ? null : trim($rationale),
                'input_type' => $inputType,
                'options' => $inputType === 'select' ? $options : null,
            ];
        }

        return [$needsClarification, $clean];
    }
}
