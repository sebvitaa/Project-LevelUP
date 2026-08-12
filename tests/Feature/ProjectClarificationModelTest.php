<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectClarification;
use App\Models\User;

it('persiste una aclaracion con sus tipos y relacion de proyecto', function () {
    $project = Project::factory()->awaitingInput()->for(User::factory())->create();
    $clarification = ProjectClarification::factory()->select()->create([
        'project_id' => $project->id,
        'generation_attempt' => $project->generation_attempt,
    ]);

    expect($project->status)->toBe(ProjectStatus::AwaitingInput)
        ->and($clarification->project->is($project))->toBeTrue()
        ->and($clarification->options)->toBe(['Opción A', 'Opción B'])
        ->and($clarification->isAnswered())->toBeFalse();
});

it('solo devuelve preguntas pendientes del intento vigente', function () {
    $project = Project::factory()->awaitingInput()->for(User::factory())->create();

    ProjectClarification::factory()->pending()->create([
        'project_id' => $project->id,
        'generation_attempt' => $project->generation_attempt,
        'key' => 'vigente',
    ]);
    ProjectClarification::factory()->answered()->create([
        'project_id' => $project->id,
        'generation_attempt' => $project->generation_attempt,
        'key' => 'respondida',
    ]);
    ProjectClarification::factory()->pending()->create([
        'project_id' => $project->id,
        'generation_attempt' => $project->generation_attempt + 1,
        'key' => 'antigua',
    ]);

    expect($project->pendingClarifications()->pluck('key')->all())->toBe(['vigente']);
});
