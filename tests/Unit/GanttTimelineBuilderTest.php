<?php

use App\Models\Activity;
use App\Models\Project;
use App\Services\Gantt\GanttTimelineBuilder;
use Illuminate\Support\Collection;

function ganttProject(int $duration, ?string $deadline = null): Project
{
    return (new Project)->forceFill([
        'id' => 7,
        'starts_on' => '2026-01-01',
        'deadline' => $deadline,
        'total_duration_days' => $duration,
    ]);
}

it('construye filas inclusivas, ordenadas y con referencias de precedencia', function () {
    $project = ganttProject(5, '2026-01-10');
    $first = (new Activity)->forceFill([
        'id' => 1,
        'code' => 'A',
        'name' => 'Inicio',
        'duration_days' => 2,
        'early_start' => 0,
        'early_finish' => 2,
        'is_critical' => true,
        'completed_at' => null,
    ]);
    $second = (new Activity)->forceFill([
        'id' => 2,
        'code' => 'B',
        'name' => 'Entrega',
        'duration_days' => 3,
        'early_start' => 2,
        'early_finish' => 5,
        'is_critical' => false,
        'completed_at' => null,
    ]);
    $second->setRelation('predecessors', collect([$first]));

    $timeline = (new GanttTimelineBuilder)->build($project, new Collection([$second, $first]));

    expect($timeline['start_date'])->toBe('2026-01-01')
        ->and($timeline['last_date'])->toBe('2026-01-10')
        ->and($timeline['total_days'])->toBe(10)
        ->and($timeline['scale'])->toBe('day')
        ->and($timeline['timeline_width'])->toBe(720)
        ->and($timeline['rows'][0]['code'])->toBe('A')
        ->and($timeline['rows'][0]['finish_date'])->toBe('2026-01-02')
        ->and($timeline['rows'][1]['start_date'])->toBe('2026-01-03')
        ->and($timeline['rows'][1]['finish_date'])->toBe('2026-01-05')
        ->and($timeline['rows'][1]['predecessors'])->toBe(['A'])
        ->and($timeline['deadline']['within_horizon'])->toBeTrue();
});

it('elige escala diaria, semanal o mensual en los límites del horizonte', function () {
    $builder = new GanttTimelineBuilder;

    expect($builder->build(ganttProject(90), new Collection)['scale'])->toBe('day')
        ->and($builder->build(ganttProject(91), new Collection)['scale'])->toBe('week')
        ->and($builder->build(ganttProject(365), new Collection)['scale'])->toBe('week')
        ->and($builder->build(ganttProject(366), new Collection)['scale'])->toBe('month');
});

it('extiende el horizonte hasta un deadline posterior y expone fines de semana', function () {
    $timeline = (new GanttTimelineBuilder)->build(
        ganttProject(2, '2026-01-05'),
        new Collection,
    );

    expect($timeline['total_days'])->toBe(5)
        ->and($timeline['deadline']['offset'])->toBe(4)
        ->and((new Collection($timeline['weekend_ranges']))->pluck('date')->all())
        ->toBe(['2026-01-03', '2026-01-04']);
});
