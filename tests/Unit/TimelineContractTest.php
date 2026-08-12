<?php

use App\Models\Activity;
use App\Models\Project;

it('trata las duraciones CPM como intervalos inclusivos de calendario', function () {
    $project = (new Project)->forceFill([
        'starts_on' => '2026-01-01',
        'total_duration_days' => 1,
    ]);
    $activity = (new Activity)->forceFill([
        'early_start' => 0,
        'early_finish' => 1,
        'duration_days' => 1,
    ]);
    $activity->setRelation('project', $project);

    expect($activity->startDate()->toDateString())->toBe('2026-01-01')
        ->and($activity->finishDate()->toDateString())->toBe('2026-01-01')
        ->and($project->projectedFinishDate()->toDateString())->toBe('2026-01-01');
});

it('convierte una cadena CPM en fechas consecutivas sin perder días', function () {
    $project = (new Project)->forceFill([
        'starts_on' => '2026-02-27',
        'total_duration_days' => 4,
    ]);

    $first = (new Activity)->forceFill([
        'early_start' => 0,
        'early_finish' => 2,
        'duration_days' => 2,
    ]);
    $second = (new Activity)->forceFill([
        'early_start' => 2,
        'early_finish' => 4,
        'duration_days' => 2,
    ]);
    $first->setRelation('project', $project);
    $second->setRelation('project', $project);

    expect($first->finishDate()->toDateString())->toBe('2026-02-28')
        ->and($second->startDate()->toDateString())->toBe('2026-03-01')
        ->and($second->finishDate()->toDateString())->toBe('2026-03-02')
        ->and($project->projectedFinishDate()->toDateString())->toBe('2026-03-02');
});

it('atraviesa correctamente un año bisiesto', function () {
    $project = (new Project)->forceFill([
        'starts_on' => '2028-02-28',
        'total_duration_days' => 2,
    ]);
    $activity = (new Activity)->forceFill([
        'early_start' => 0,
        'early_finish' => 2,
        'duration_days' => 2,
    ]);
    $activity->setRelation('project', $project);

    expect($activity->finishDate()->toDateString())->toBe('2028-02-29')
        ->and($project->projectedFinishDate()->toDateString())->toBe('2028-02-29');
});

it('calcula correctamente el atraso cuando el término queda antes, igual o después del deadline', function () {
    $project = (new Project)->forceFill([
        'starts_on' => '2026-01-01',
        'total_duration_days' => 10,
    ]);

    $project->deadline = '2026-01-12';
    expect($project->daysBehindSchedule())->toBe(-2);

    $project->deadline = '2026-01-10';
    expect($project->daysBehindSchedule())->toBe(0);

    $project->deadline = '2026-01-08';
    expect($project->daysBehindSchedule())->toBe(2);
});
