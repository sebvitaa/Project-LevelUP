<?php

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Jobs\GenerateProjectSchedule;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('muestra los tipos de proyecto en el paso 1', function () {
    $this->actingAs($this->user)
        ->get(route('projects.create.type'))
        ->assertOk()
        ->assertSee('Desarrollo de software')
        ->assertSee('Construcción y obra');
});

it('guarda el tipo elegido y avanza al paso 2', function () {
    $this->actingAs($this->user)
        ->post(route('projects.store.type'), ['type' => ProjectType::Software->value])
        ->assertRedirect(route('projects.create.prompt'))
        ->assertSessionHas('project_wizard.type', ProjectType::Software->value);
});

it('devuelve al paso 1 si se entra al prompt sin haber elegido tipo', function () {
    $this->actingAs($this->user)
        ->get(route('projects.create.prompt'))
        ->assertRedirect(route('projects.create.type'));
});

it('crea el proyecto y encola la generación', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('projects.store'), [
            'name' => 'App Banca Móvil',
            'type' => ProjectType::Software->value,
            'prompt' => 'Necesito lanzar la app móvil de banca personal para iOS y Android, desde requerimientos hasta publicación.',
            'starts_on' => '2026-08-06',
            'deadline' => '2026-09-14',
            'team_size' => 5,
        ])
        ->assertRedirect();

    $project = Project::sole();

    expect($project->name)->toBe('App Banca Móvil')
        ->and($project->status)->toBe(ProjectStatus::Generating)
        ->and($project->user_id)->toBe($this->user->id);

    Queue::assertPushed(GenerateProjectSchedule::class);
});

it('exige una descripción con contenido suficiente', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('projects.store'), [
            'name' => 'App',
            'type' => ProjectType::Software->value,
            'prompt' => 'algo corto',
            'starts_on' => '2026-08-06',
        ])
        ->assertSessionHasErrors('prompt');

    Queue::assertNothingPushed();
});

it('exige que la fecha límite sea posterior al inicio', function () {
    $this->actingAs($this->user)
        ->post(route('projects.store'), [
            'name' => 'App Banca Móvil',
            'type' => ProjectType::Software->value,
            'prompt' => 'Necesito lanzar la app móvil de banca personal para iOS y Android, desde requerimientos hasta publicación.',
            'starts_on' => '2026-08-06',
            'deadline' => '2026-08-01',
        ])
        ->assertSessionHasErrors('deadline');
});

it('bloquea la creación cuando no quedan consultas', function () {
    $userWithoutCredits = User::factory()->withoutAiCredits()->create();

    $this->actingAs($userWithoutCredits)
        ->post(route('projects.store'), [
            'name' => 'App Banca Móvil',
            'type' => ProjectType::Software->value,
            'prompt' => 'Necesito lanzar la app móvil de banca personal para iOS y Android, desde requerimientos hasta publicación.',
            'starts_on' => '2026-08-06',
        ])
        ->assertForbidden();
});

it('impide ver la malla de un proyecto ajeno', function () {
    $theirs = Project::factory()->ready()->create();

    $this->actingAs($this->user)
        ->get(route('projects.show', $theirs))
        ->assertForbidden();
});
