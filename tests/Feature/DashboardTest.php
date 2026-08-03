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
