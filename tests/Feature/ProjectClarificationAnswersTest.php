<?php

use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Jobs\GenerateProjectSchedule;
use App\Models\Project;
use App\Models\ProjectClarification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->awaitingInput()->for($this->user)->create();
    $this->textQuestion = ProjectClarification::factory()->create([
        'project_id' => $this->project->getKey(),
        'generation_attempt' => $this->project->generation_attempt,
        'key' => 'alcance',
        'question' => '¿Qué alcance debe cubrir?',
        'input_type' => 'text',
        'options' => null,
    ]);
    $this->selectQuestion = ProjectClarification::factory()->select()->create([
        'project_id' => $this->project->getKey(),
        'generation_attempt' => $this->project->generation_attempt,
        'key' => 'plataforma',
        'question' => '¿Qué plataforma es prioritaria?',
    ]);
});

it('muestra las preguntas al dueño del proyecto', function () {
    $this->actingAs($this->user)
        ->get(route('projects.generating', $this->project))
        ->assertOk()
        ->assertSee('¿Qué alcance debe cubrir?')
        ->assertSee('¿Qué plataforma es prioritaria?')
        ->assertSee('<textarea', false)
        ->assertSee('type="radio"', false)
        ->assertSee('min-h-full', false)
        ->assertSee('justify-start', false)
        ->assertSee('Continuar con la generación');
});

it('impide que un tercero vea o responda las preguntas', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('projects.generating', $this->project))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->post(route('projects.clarifications.store', $this->project), [
            'answers' => [
                $this->textQuestion->getKey() => 'Respuesta ajena',
                $this->selectQuestion->getKey() => 'Opción A',
            ],
        ])
        ->assertForbidden();
});

it('guarda todas las respuestas y reanuda la generación final', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('projects.clarifications.store', $this->project), [
            'answers' => [
                $this->textQuestion->getKey() => '  Alcance completo  ',
                $this->selectQuestion->getKey() => 'Opción A',
            ],
        ])
        ->assertRedirect(route('projects.generating', $this->project));

    $project = $this->project->refresh();

    expect($project->status)->toBe(ProjectStatus::Generating)
        ->and($project->generation_stage)->toBe(ProjectGenerationStage::RequestingPlan)
        ->and($project->pendingClarifications()->count())->toBe(0)
        ->and($this->textQuestion->refresh()->answer)->toBe('Alcance completo')
        ->and($this->textQuestion->answered_at)->not->toBeNull()
        ->and($this->selectQuestion->refresh()->answer)->toBe('Opción A');

    Queue::assertPushed(GenerateProjectSchedule::class, function (GenerateProjectSchedule $job) use ($project): bool {
        return $job->projectId === $project->getKey()
            && $job->generationAttempt === $project->generation_attempt;
    });
});

it('rechaza respuestas faltantes sin cambiar el proyecto', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('projects.clarifications.store', $this->project), [
            'answers' => [
                $this->textQuestion->getKey() => 'Solo una respuesta',
            ],
        ])
        ->assertSessionHasErrors('answers');

    expect($this->project->refresh()->status)->toBe(ProjectStatus::AwaitingInput)
        ->and($this->textQuestion->refresh()->answered_at)->toBeNull()
        ->and($this->selectQuestion->refresh()->answered_at)->toBeNull();

    Queue::assertNothingPushed();
});

it('rechaza una opción que no pertenece al select', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('projects.clarifications.store', $this->project), [
            'answers' => [
                $this->textQuestion->getKey() => 'Respuesta válida',
                $this->selectQuestion->getKey() => 'Opción inventada',
            ],
        ])
        ->assertSessionHasErrors('answers.'.$this->selectQuestion->getKey());

    expect($this->project->refresh()->status)->toBe(ProjectStatus::AwaitingInput);
    Queue::assertNothingPushed();
});

it('rechaza ids que no pertenecen a las preguntas pendientes', function () {
    Queue::fake();
    $otherProject = Project::factory()->awaitingInput()->for($this->user)->create();
    $otherQuestion = ProjectClarification::factory()->create([
        'project_id' => $otherProject->getKey(),
        'generation_attempt' => $otherProject->generation_attempt,
    ]);

    $this->actingAs($this->user)
        ->post(route('projects.clarifications.store', $this->project), [
            'answers' => [
                $this->textQuestion->getKey() => 'Respuesta válida',
                $otherQuestion->getKey() => 'Respuesta ajena',
            ],
        ])
        ->assertSessionHasErrors('answers');

    expect($this->project->refresh()->status)->toBe(ProjectStatus::AwaitingInput);
    Queue::assertNothingPushed();
});
