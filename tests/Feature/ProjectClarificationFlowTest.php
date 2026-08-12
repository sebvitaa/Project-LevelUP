<?php

use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Jobs\GenerateProjectClarifications;
use App\Jobs\GenerateProjectSchedule;
use App\Models\Project;
use App\Models\ProjectClarification;
use App\Models\User;
use App\Services\Ai\ProjectClarificationGenerator;
use App\Services\Ai\ProjectPlanGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @param  bool  $needsClarification
 * @param  array<int, array<string, mixed>>  $questions
 * @return array<string, mixed>
 */
function clarificationGeminiReply(bool $needsClarification, array $questions = []): array
{
    return [
        'candidates' => [[
            'content' => ['parts' => [[
                'text' => json_encode([
                    'needs_clarification' => $needsClarification,
                    'questions' => $questions,
                ]),
            ]]],
        ]],
    ];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->clarifying()->for($this->user)->create();
});

it('persiste entre una y tres preguntas y espera las respuestas', function () {
    Http::fake(['*' => Http::response(clarificationGeminiReply(true, [
        [
            'key' => 'alcance_integraciones',
            'question' => '¿Qué integraciones externas debe incluir el lanzamiento?',
            'rationale' => 'Cambia las actividades y dependencias de integración.',
            'input_type' => 'text',
            'options' => [],
        ],
    ]))]);

    (new GenerateProjectClarifications($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectClarificationGenerator::class));

    $project = $this->project->refresh();
    $clarification = $project->clarifications->sole();

    expect($project->status)->toBe(ProjectStatus::AwaitingInput)
        ->and($project->generation_stage)->toBe(ProjectGenerationStage::AwaitingAnswers)
        ->and($clarification->key)->toBe('alcance_integraciones')
        ->and($clarification->generation_attempt)->toBe(1)
        ->and($clarification->isAnswered())->toBeFalse()
        ->and($this->user->refresh()->ai_credits_used)->toBe(0);
});

it('pasa directamente a la generación final cuando el brief es suficiente', function () {
    Queue::fake();
    Http::fake(['*' => Http::response(clarificationGeminiReply(false))]);

    (new GenerateProjectClarifications($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectClarificationGenerator::class));

    expect($this->project->refresh()->status)->toBe(ProjectStatus::Generating)
        ->and($this->project->generation_stage)->toBe(ProjectGenerationStage::RequestingPlan);

    Queue::assertPushed(GenerateProjectSchedule::class, function (GenerateProjectSchedule $job): bool {
        return $job->projectId === $this->project->getKey()
            && $job->generationAttempt === $this->project->generation_attempt;
    });
});

it('rechaza una respuesta con más de tres preguntas', function () {
    Http::fake(['*' => Http::response(clarificationGeminiReply(true, array_map(
        fn (int $number): array => [
            'key' => 'pregunta_'.$number,
            'question' => 'Pregunta '.$number,
            'rationale' => 'Razon '.$number,
            'input_type' => 'text',
            'options' => [],
        ],
        range(1, 4),
    )))]);

    (new GenerateProjectClarifications($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectClarificationGenerator::class));

    expect($this->project->refresh()->status)->toBe(ProjectStatus::Failed)
        ->and($this->project->generation_error)->toContain('aclaraciones');
});

it('ignora el resultado si el intento cambió durante la llamada', function () {
    Http::fake(function () {
        $this->project->refresh()->forceFill(['generation_attempt' => 2])->save();

        return Http::response(clarificationGeminiReply(true, [[
            'key' => 'alcance',
            'question' => '¿Qué alcance tendrá?',
            'rationale' => 'Define el tamaño del plan.',
            'input_type' => 'text',
            'options' => [],
        ]]));
    });

    Queue::fake();

    (new GenerateProjectClarifications($this->project->getKey(), 1))
        ->handle(app(ProjectClarificationGenerator::class));

    expect($this->project->refresh()->status)->toBe(ProjectStatus::Clarifying)
        ->and($this->project->generation_attempt)->toBe(2)
        ->and($this->project->clarifications)->toHaveCount(0);

    Queue::assertNothingPushed();
});

it('termina sin efectos si el proyecto fue eliminado antes del job de aclaraciones', function () {
    Http::fake();

    $projectId = $this->project->getKey();
    $this->project->delete();

    (new GenerateProjectClarifications($projectId, 1))
        ->handle(app(ProjectClarificationGenerator::class));

    Http::assertNothingSent();
    expect(Project::query()->find($projectId))->toBeNull();
});

it('anexa las respuestas confirmadas al prompt final', function () {
    $project = Project::factory()->generating()->for($this->user)->create();
    ProjectClarification::factory()->answered('REST y pagos')
        ->create([
            'project_id' => $project->getKey(),
            'generation_attempt' => $project->generation_attempt,
            'key' => 'integraciones',
            'question' => '¿Qué integraciones externas debe incluir?',
            'input_type' => 'text',
        ]);

    Http::fake(['*' => Http::response([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode([
                'activities' => array_map(
                    fn (string $code): array => [
                        'code' => $code,
                        'name' => 'Actividad '.$code,
                        'description' => 'Descripción '.$code,
                        'duration_days' => 1,
                        'predecessors' => [],
                    ],
                    range('A', 'H'),
                ),
            ])]]],
        ]],
    ])]);

    (new GenerateProjectSchedule($project->getKey(), $project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    Http::assertSent(function (Request $request): bool {
        $prompt = $request->data()['contents'][0]['parts'][0]['text'];

        return str_contains($prompt, 'Aclaraciones confirmadas:')
            && str_contains($prompt, '¿Qué integraciones externas debe incluir?')
            && str_contains($prompt, 'Respuesta: REST y pagos');
    });
});
