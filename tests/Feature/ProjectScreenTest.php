<?php

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoProjectSeeder;

/**
 * Humo sobre las pantallas 02 a 06 usando el proyecto de demostración, que es
 * la misma malla dibujada en los mockups.
 */
beforeEach(function () {
    $this->seed(DemoProjectSeeder::class);

    $this->project = Project::sole();
    $this->user = $this->project->user;
});

it('dibuja la malla con los tiempos de cada actividad', function () {
    $this->actingAs($this->user)
        ->get(route('projects.show', $this->project))
        ->assertOk()
        ->assertSee('Desarrollo de la API')
        ->assertSee('ES 11')
        ->assertSee('EF 23')
        ->assertSee('39 días');
});

it('deja seleccionar una actividad concreta desde su nodo', function () {
    $this->actingAs($this->user)
        ->get(route('projects.show', ['project' => $this->project, 'activity' => 'B']))
        ->assertOk()
        ->assertSee('Diseño UX/UI')
        ->assertSee('Holgura 6 d');
});

it('usa la actividad crítica por defecto cuando el código solicitado no existe', function () {
    $this->actingAs($this->user)
        ->get(route('projects.show', ['project' => $this->project, 'activity' => 'NO-EXISTE']))
        ->assertOk()
        ->assertSee('id="activity-A"', false)
        ->assertSee('aria-current="true"', false);
});

it('muestra la vista Gantt y conserva la actividad seleccionada', function () {
    $response = $this->actingAs($this->user)
        ->get(route('projects.show', [
            'project' => $this->project,
            'view' => 'gantt',
            'activity' => 'B',
        ]))
        ->assertOk()
        ->assertSee('Carta Gantt')
        ->assertSee('Diseño UX/UI')
        ->assertSee('Después de')
        ->assertSee('>E</span>', false)
        ->assertSee('>F</span>', false)
        ->assertSee('view=gantt', false);

    expect(substr_count($response->getContent(), 'activity=B'))->toBeGreaterThanOrEqual(2);
});

it('manda a la pantalla de espera si la malla todavía no está lista', function () {
    $pending = Project::factory()->generating()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('projects.show', $pending))
        ->assertRedirect(route('projects.generating', $pending));
});

it('marca una actividad como hecha y lo refleja en el avance', function () {
    $activity = $this->project->activities()->where('code', 'A')->sole();

    $this->actingAs($this->user)
        ->post(route('activities.toggle', $activity))
        ->assertRedirect();

    expect($activity->refresh()->isCompleted())->toBeTrue();

    // 1 de 8 actividades completadas.
    $this->get(route('dashboard'))->assertSee('13%');

    $this->get(route('projects.show', ['project' => $this->project, 'activity' => 'A']))
        ->assertSee('data-completed="true"', false)
        ->assertSee('bg-done-soft', false)
        ->assertSee('✓ Hecha')
        ->assertSee('crítica');

    $this->get(route('projects.show', [
        'project' => $this->project,
        'view' => 'gantt',
        'activity' => 'A',
    ]))
        ->assertSee('bg-done', false)
        ->assertSee('completada', false);

    $this->get(route('projects.show', [
        'project' => $this->project,
        'view' => 'gantt',
        'activity' => 'C',
    ]))
        ->assertSee('bg-slack', false);
});

it('marca visualmente una actividad vencida y adapta el nodo a nombres largos', function () {
    $this->project->forceFill(['starts_on' => now()->subDays(10)->toDateString()])->save();
    $overdue = $this->project->activities()->where('code', 'A')->sole();
    $overdue->setRelation('project', $this->project->refresh());

    $this->actingAs($this->user)
        ->get(route('projects.show', ['project' => $this->project, 'activity' => 'A']))
        ->assertOk()
        ->assertSee('data-overdue="true"', false)
        ->assertSee('bg-critical-soft', false)
        ->assertSee('Atrasada')
        ->assertSee('width: 220px; height: 104px;', false)
        ->assertSee('line-clamp-3', false);

    expect($overdue->isOverdue())->toBeTrue();
});

it('permite devolver una actividad completada a pendiente', function () {
    $activity = $this->project->activities()->where('code', 'A')->sole();

    $this->actingAs($this->user)->post(route('activities.toggle', $activity));
    $this->actingAs($this->user)->post(route('activities.toggle', $activity));

    $this->actingAs($this->user)
        ->get(route('projects.show', ['project' => $this->project, 'activity' => 'A']))
        ->assertSee('data-completed="false"', false)
        ->assertDontSee('✓ Hecha');
});

it('conserva Gantt al marcar una actividad como hecha', function () {
    $activity = $this->project->activities()->where('code', 'A')->sole();

    $this->actingAs($this->user)
        ->post(route('activities.toggle', ['activity' => $activity, 'view' => 'gantt']))
        ->assertRedirect(route('projects.show', [
            'project' => $this->project,
            'activity' => 'A',
            'view' => 'gantt',
        ]));
});

it('el dueño puede eliminar el proyecto y sus datos dependientes', function () {
    $project = Project::factory()->ready()->for($this->user)->create();
    $activity = $project->activities()->create([
        'code' => 'Z',
        'name' => 'Actividad temporal',
        'description' => 'Se elimina con el proyecto.',
        'duration_days' => 1,
    ]);
    $dependent = $project->activities()->create([
        'code' => 'Y',
        'name' => 'Dependiente temporal',
        'description' => null,
        'duration_days' => 1,
    ]);
    $dependent->predecessors()->attach($activity->getKey());
    $clarification = $project->clarifications()->create([
        'round' => 1,
        'generation_attempt' => $project->generation_attempt,
        'key' => 'temporal',
        'question' => 'Pregunta temporal',
        'input_type' => 'text',
    ]);

    $this->actingAs($this->user)
        ->get(route('projects.show', $project))
        ->assertSee('Eliminar')
        ->assertSee('Eliminar permanentemente el proyecto '.$project->name, false);

    $this->actingAs($this->user)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('projects', ['id' => $project->getKey()]);
    $this->assertDatabaseMissing('activities', ['id' => $activity->getKey()]);
    $this->assertDatabaseMissing('activity_dependencies', ['activity_id' => $dependent->getKey()]);
    $this->assertDatabaseMissing('project_clarifications', ['id' => $clarification->getKey()]);
});

it('no permite eliminar proyectos de otro usuario', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->delete(route('projects.destroy', $this->project))
        ->assertForbidden();
});

it('recalcula la ruta crítica al cambiar una duración', function () {
    // B tiene 6 días de holgura. Al subirla de 8 a 15 días, su rama
    // (A→B→E→G→H = 40 d) supera a la anterior ruta crítica de 39 d.
    $design = $this->project->activities()->where('code', 'B')->sole();

    $this->actingAs($this->user)
        ->patch(route('activities.update', $design), [
            'name' => $design->name,
            'description' => $design->description,
            'duration_days' => 15,
        ])
        ->assertRedirect();

    expect($design->refresh()->is_critical)->toBeTrue()
        ->and($design->slack)->toBe(0)
        ->and($this->project->refresh()->total_duration_days)->toBe(40);

    // Y la rama que antes era crítica pasa a tener holgura.
    expect($this->project->activities()->where('code', 'D')->sole()->slack)->toBe(1);
});

it('no deja a un usuario editar actividades de otro', function () {
    $intruder = User::factory()->create();
    $activity = $this->project->activities()->first();

    $this->actingAs($intruder)
        ->post(route('activities.toggle', $activity))
        ->assertForbidden();
});
