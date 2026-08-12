<?php

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;

it('lista solo los proyectos del usuario autenticado', function () {
    $user = User::factory()->create();
    $mine = Project::factory()->ready()->for($user)->create(['name' => 'App Banca Móvil']);
    $theirs = Project::factory()->ready()->create(['name' => 'Proyecto Ajeno']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($mine->name)
        ->assertDontSee($theirs->name);
});

it('calcula el avance a partir de las actividades completadas', function () {
    $user = User::factory()->create();
    $project = Project::factory()->ready()->for($user)->create();

    Activity::factory()->count(3)->completed()->for($project)->create();
    Activity::factory()->count(1)->for($project)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('75%');
});

it('muestra 0% cuando el proyecto todavía no tiene malla', function () {
    $user = User::factory()->create();
    Project::factory()->draft()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Falta generar la malla');
});

it('invita a crear el primero cuando no hay proyectos', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Todavía no tienes proyectos');
});

it('filtra proyectos en riesgo y completados', function () {
    $user = User::factory()->create();
    $risk = Project::factory()->ready()->for($user)->create([
        'name' => 'Proyecto en riesgo',
        'starts_on' => now()->subDays(30),
        'deadline' => now()->subDay(),
        'total_duration_days' => 40,
    ]);
    Activity::factory()->for($risk)->create();
    $done = Project::factory()->ready()->for($user)->create(['name' => 'Proyecto terminado']);
    Activity::factory()->completed()->for($done)->create();

    $this->actingAs($user)->get(route('dashboard', ['filtro' => 'riesgo']))
        ->assertOk()->assertSee('Proyecto en riesgo')->assertDontSee('Proyecto terminado');
    $this->actingAs($user)->get(route('dashboard', ['filtro' => 'completados']))
        ->assertOk()->assertSee('Proyecto terminado')->assertDontSee('Proyecto en riesgo');
});

it('busca por proyecto o actividad sin mostrar proyectos ajenos', function () {
    $user = User::factory()->create();
    $project = Project::factory()->ready()->for($user)->create(['name' => 'Migración interna']);
    Activity::factory()->for($project)->create(['name' => 'Conectar facturación']);
    Project::factory()->ready()->for($user)->create(['name' => 'Sitio corporativo']);
    $other = Project::factory()->ready()->create(['name' => 'Facturación ajena']);

    $this->actingAs($user)->get(route('dashboard', ['q' => 'facturación']))
        ->assertOk()->assertSee('Migración interna')->assertDontSee('Sitio corporativo')
        ->assertDontSee($other->name);
});

it('ordena la cartera por nombre', function () {
    $user = User::factory()->create();
    Project::factory()->ready()->for($user)->create(['name' => 'Zulu']);
    Project::factory()->ready()->for($user)->create(['name' => 'Alfa']);

    $this->actingAs($user)->get(route('dashboard', ['orden' => 'nombre']))
        ->assertOk()->assertSeeInOrder(['Alfa', 'Zulu']);
});
