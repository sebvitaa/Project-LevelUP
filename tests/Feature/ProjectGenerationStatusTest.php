<?php

use App\Enums\ProjectGenerationStage;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('devuelve el contrato de progreso sin cachear', function () {
    $project = Project::factory()->generating()->for($this->user)->create([
        'generation_stage' => ProjectGenerationStage::ValidatingPlan,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('projects.status', $project));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store')
        ->assertJson([
            'status' => 'generating',
            'stage' => 'validating_plan',
            'step_index' => 3,
            'step_count' => 5,
            'message' => 'Validando el plan',
            'is_terminal' => false,
            'needs_input' => false,
            'is_stalled' => false,
            'error' => null,
            'redirect_to' => null,
        ]);

    expect($response->json('started_at'))->not->toBeNull()
        ->and($response->json('last_progress_at'))->not->toBeNull();
});

it('marca como estancada una generación activa sin progreso reciente', function () {
    config(['levelup.generation_stalled_after' => 120]);
    $project = Project::factory()->generating()->for($this->user)->create([
        'generation_stage' => ProjectGenerationStage::RequestingPlan,
        'generation_progressed_at' => now()->subSeconds(121),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('projects.status', $project))
        ->assertJsonPath('is_stalled', true)
        ->assertJsonPath('step_index', 2);
});

it('expone que el proyecto necesita respuestas sin considerarlo terminal', function () {
    $project = Project::factory()->awaitingInput()->for($this->user)->create();

    $this->actingAs($this->user)
        ->getJson(route('projects.status', $project))
        ->assertJson([
            'status' => 'awaiting_input',
            'stage' => 'awaiting_answers',
            'step_index' => 2,
            'needs_input' => true,
            'is_terminal' => false,
            'is_stalled' => false,
            'redirect_to' => null,
        ]);
});

it('activa el watcher solo mientras la generación puede avanzar', function () {
    $active = Project::factory()->generating()->for($this->user)->create();
    $clarifying = Project::factory()->clarifying()->for($this->user)->create();
    $awaiting = Project::factory()->awaitingInput()->for($this->user)->create();
    $failed = Project::factory()->failed()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('projects.generating', $active))
        ->assertSee('id="generation-watcher"', false)
        ->assertSee('data-watcher-active="true"', false)
        ->assertSee('role="progressbar"', false)
        ->assertSee('id="generation-progress-message"', false)
        ->assertSee('id="generation-progress-fill"', false)
        ->assertSee('data-generation-step="1"', false);

    $this->actingAs($this->user)
        ->get(route('projects.generating', $clarifying))
        ->assertSee('data-watcher-active="true"', false);

    $this->actingAs($this->user)
        ->get(route('projects.generating', $awaiting))
        ->assertSee('data-watcher-active="false"', false);

    $this->actingAs($this->user)
        ->get(route('projects.generating', $failed))
        ->assertSee('data-watcher-active="false"', false);
});

it('redirecciona un borrador al dashboard y un tercero no puede consultar el estado', function () {
    $draft = Project::factory()->draft()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('projects.generating', $draft))
        ->assertRedirect(route('dashboard'));

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->getJson(route('projects.status', $draft))
        ->assertForbidden();
});
