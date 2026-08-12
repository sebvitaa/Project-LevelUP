<?php

use App\Enums\ProjectStatus;
use App\Jobs\GenerateProjectSchedule;
use App\Models\Project;
use App\Models\User;
use App\Services\Ai\ProjectPlanGenerator;
use Illuminate\Support\Facades\Http;

/**
 * Respuesta de Gemini simulada, con la estructura del responseSchema.
 *
 * @param  array<int, array<string, mixed>>  $activities
 * @return array<string, mixed>
 */
function geminiReply(array $activities): array
{
    return [
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode(['activities' => $activities])]]],
        ]],
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function samplePlan(): array
{
    return [
        ['code' => 'A', 'name' => 'Requerimientos', 'description' => 'Levantar el alcance.', 'duration_days' => 5, 'predecessors' => []],
        ['code' => 'B', 'name' => 'Diseño', 'description' => 'Diseñar las pantallas.', 'duration_days' => 8, 'predecessors' => ['A']],
        ['code' => 'C', 'name' => 'Arquitectura', 'description' => 'Definir servicios.', 'duration_days' => 6, 'predecessors' => ['A']],
        ['code' => 'D', 'name' => 'API', 'description' => 'Construir endpoints.', 'duration_days' => 12, 'predecessors' => ['C']],
        ['code' => 'E', 'name' => 'Frontend', 'description' => 'Implementar la app.', 'duration_days' => 10, 'predecessors' => ['B']],
        ['code' => 'F', 'name' => 'Pagos', 'description' => 'Integrar la pasarela.', 'duration_days' => 6, 'predecessors' => ['D']],
        ['code' => 'G', 'name' => 'QA', 'description' => 'Probar todo.', 'duration_days' => 7, 'predecessors' => ['E', 'F']],
        ['code' => 'H', 'name' => 'Publicación', 'description' => 'Subir a las tiendas.', 'duration_days' => 3, 'predecessors' => ['G']],
    ];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->generating()->for($this->user)->create();
});

it('guarda la malla con la ruta crítica ya resuelta', function () {
    Http::fake(['*' => Http::response(geminiReply(samplePlan()))]);

    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    $this->project->refresh();

    expect($this->project->status)->toBe(ProjectStatus::Ready)
        ->and($this->project->generation_stage)->toBe(\App\Enums\ProjectGenerationStage::Complete)
        ->and($this->project->charged_generation_attempt)->toBe($this->project->generation_attempt)
        ->and($this->project->total_duration_days)->toBe(39)
        ->and($this->project->activities)->toHaveCount(8);

    $critical = $this->project->activities()->where('is_critical', true)->pluck('code')->sort()->values()->all();

    expect($critical)->toBe(['A', 'C', 'D', 'F', 'G', 'H']);
});

it('guarda las dependencias entre actividades', function () {
    Http::fake(['*' => Http::response(geminiReply(samplePlan()))]);

    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    $qa = $this->project->activities()->where('code', 'G')->sole();

    expect($qa->predecessors->pluck('code')->sort()->values()->all())->toBe(['E', 'F'])
        ->and($qa->successors->pluck('code')->all())->toBe(['H']);
});

it('descuenta una consulta solo si la generación resultó', function () {
    Http::fake(['*' => Http::response(geminiReply(samplePlan()))]);

    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    expect($this->user->refresh()->ai_credits_used)->toBe(1);
});

it('marca el proyecto como fallido si la API se cae, sin cobrar la consulta', function () {
    Http::fake(['*' => Http::response(status: 503)]);

    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    $this->project->refresh();

    expect($this->project->status)->toBe(ProjectStatus::Failed)
        ->and($this->project->generation_error)->toContain('No pudimos contactar al servicio de IA')
        ->and($this->user->refresh()->ai_credits_used)->toBe(0);
});

it('rechaza un plan con dependencias circulares', function () {
    Http::fake(['*' => Http::response(geminiReply([
        ['code' => 'A', 'name' => 'Uno', 'description' => '', 'duration_days' => 2, 'predecessors' => ['B']],
        ['code' => 'B', 'name' => 'Dos', 'description' => '', 'duration_days' => 2, 'predecessors' => ['A']],
        ['code' => 'C', 'name' => 'Tres', 'description' => '', 'duration_days' => 2, 'predecessors' => []],
        ['code' => 'D', 'name' => 'Cuatro', 'description' => '', 'duration_days' => 2, 'predecessors' => []],
        ['code' => 'E', 'name' => 'Cinco', 'description' => '', 'duration_days' => 2, 'predecessors' => []],
        ['code' => 'F', 'name' => 'Seis', 'description' => '', 'duration_days' => 2, 'predecessors' => []],
        ['code' => 'G', 'name' => 'Siete', 'description' => '', 'duration_days' => 2, 'predecessors' => []],
        ['code' => 'H', 'name' => 'Ocho', 'description' => '', 'duration_days' => 2, 'predecessors' => []],
    ]))]);

    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    $this->project->refresh();

    expect($this->project->status)->toBe(ProjectStatus::Failed)
        ->and($this->project->generation_error)->toContain('no forma una malla válida')
        ->and($this->project->activities)->toHaveCount(0);
});

it('rechaza una respuesta sin actividades', function () {
    Http::fake(['*' => Http::response(geminiReply([]))]);

    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    expect($this->project->refresh()->status)->toBe(ProjectStatus::Failed);
});

it('no llama a la IA si no quedan consultas', function () {
    Http::fake();
    $this->user->forceFill(['ai_credits_used' => $this->user->ai_credits_limit])->save();

    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    Http::assertNothingSent();

    expect($this->project->refresh()->generation_error)->toContain('Se acabaron tus consultas');
});

it('reemplaza la malla anterior al regenerar', function () {
    Http::fake(['*' => Http::response(geminiReply(samplePlan()))]);

    $generator = app(ProjectPlanGenerator::class);
    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle($generator);
    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle($generator);

    expect($this->project->refresh()->activities)->toHaveCount(8)
        ->and($this->project->charged_generation_attempt)->toBe($this->project->generation_attempt)
        ->and($this->user->refresh()->ai_credits_used)->toBe(1);
});

it('no cobra ni deja lista la malla si el ultimo credito fue consumido antes de persistir', function () {
    Http::fake(function () {
        $this->user->refresh()->forceFill([
            'ai_credits_used' => $this->user->ai_credits_limit,
        ])->save();

        return Http::response(geminiReply(samplePlan()));
    });

    (new GenerateProjectSchedule($this->project->getKey(), $this->project->generation_attempt))
        ->handle(app(ProjectPlanGenerator::class));

    expect($this->project->refresh()->status)->toBe(ProjectStatus::Failed)
        ->and($this->project->charged_generation_attempt)->toBeNull()
        ->and($this->user->refresh()->ai_credits_used)->toBe($this->user->ai_credits_limit);
});

it('ignora un job de un intento anterior', function () {
    Http::fake();

    $this->project->forceFill([
        'generation_attempt' => 2,
        'generation_error' => null,
    ])->save();

    (new GenerateProjectSchedule($this->project->getKey(), 1))
        ->handle(app(ProjectPlanGenerator::class));

    Http::assertNothingSent();

    expect($this->project->refresh()->generation_attempt)->toBe(2)
        ->and($this->project->status)->toBe(ProjectStatus::Generating);
});

it('no permite que un job antiguo marque como fallido un proyecto listo', function () {
    $this->project->forceFill([
        'status' => ProjectStatus::Ready,
        'generation_attempt' => 2,
    ])->save();

    (new GenerateProjectSchedule($this->project->getKey(), 1))
        ->failed(new RuntimeException('old job'));

    expect($this->project->refresh()->status)->toBe(ProjectStatus::Ready)
        ->and($this->project->generation_attempt)->toBe(2);
});

it('no persiste la respuesta si el intento cambia durante la llamada a la IA', function () {
    Http::fake(function () {
        $this->project->refresh()->forceFill([
            'generation_attempt' => 2,
        ])->save();

        return Http::response(geminiReply(samplePlan()));
    });

    (new GenerateProjectSchedule($this->project->getKey(), 1))
        ->handle(app(ProjectPlanGenerator::class));

    expect($this->project->refresh()->status)->toBe(ProjectStatus::Generating)
        ->and($this->project->activities)->toHaveCount(0)
        ->and($this->user->refresh()->ai_credits_used)->toBe(0);
});
