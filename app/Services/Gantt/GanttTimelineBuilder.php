<?php

namespace App\Services\Gantt;

use App\Models\Activity;
use App\Models\Project;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Construye el modelo temporal del Gantt sin persistir ni consultar la base.
 */
class GanttTimelineBuilder
{
    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<string, mixed>
     */
    public function build(Project $project, Collection $activities): array
    {
        $start = $project->starts_on->copy()->startOfDay();
        $calculatedDays = max(1, (int) $project->total_duration_days);
        $calculatedLast = $start->copy()->addDays($calculatedDays - 1);
        $deadline = $project->deadline?->copy()->startOfDay();
        $last = $deadline !== null && $deadline->greaterThan($calculatedLast)
            ? $deadline
            : $calculatedLast;
        $days = $start->diffInDays($last) + 1;
        $scale = $this->scaleFor($days);
        $dayWidth = match ($scale) {
            'day' => 40,
            'week' => 16,
            default => 5,
        };

        $rows = $activities
            ->sortBy(fn (Activity $activity): array => [
                $activity->early_start ?? PHP_INT_MAX,
                $activity->early_finish ?? PHP_INT_MAX,
                $activity->code,
            ])
            ->values()
            ->map(function (Activity $activity) use ($project, $start): array {
                $offset = (int) $activity->early_start;
                $duration = (int) $activity->duration_days;
                $activityStart = $start->copy()->addDays($offset);
                $activityFinish = $activityStart->copy()->addDays($duration - 1);
                $isCompleted = $activity->isCompleted();
                $predecessors = $activity->relationLoaded('predecessors')
                    ? $activity->predecessors->pluck('code')->values()->all()
                    : [];

                return [
                    'id' => $activity->getKey(),
                    'code' => $activity->code,
                    'name' => $activity->name,
                    'description' => $activity->description,
                    'duration_days' => $duration,
                    'early_start' => $activity->early_start,
                    'early_finish' => $activity->early_finish,
                    'start_date' => $activityStart->toDateString(),
                    'finish_date' => $activityFinish->toDateString(),
                    'offset' => $offset,
                    'duration' => $duration,
                    'is_critical' => (bool) $activity->is_critical,
                    'is_completed' => $isCompleted,
                    'is_overdue' => ! $isCompleted && $activityFinish->isBefore(Carbon::now()->startOfDay()),
                    'predecessors' => $predecessors,
                    'project_id' => $project->getKey(),
                ];
            });

        return [
            'start_date' => $start->toDateString(),
            'last_date' => $last->toDateString(),
            'calculated_last_date' => $calculatedLast->toDateString(),
            'total_days' => $days,
            'calculated_duration_days' => $calculatedDays,
            'scale' => $scale,
            'timeline_width' => max(720, $days * $dayWidth),
            'months' => $this->groupsByMonth($start, $days),
            'columns' => $this->columns($start, $days, $scale),
            'weekend_ranges' => $this->weekendRanges($start, $days),
            'today' => $this->marker($start, $days, Carbon::now()->startOfDay()),
            'deadline' => $deadline ? $this->marker($start, $days, $deadline) : null,
            'rows' => $rows->all(),
        ];
    }

    private function scaleFor(int $days): string
    {
        return match (true) {
            $days <= 90 => 'day',
            $days <= 365 => 'week',
            default => 'month',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupsByMonth($start, int $days): array
    {
        $groups = [];
        $period = CarbonPeriod::create($start, $start->copy()->addDays($days - 1));

        foreach ($period as $date) {
            $key = $date->format('Y-m');
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $date->translatedFormat('F Y'),
                    'offset' => $start->diffInDays($date),
                    'duration' => 0,
                ];
            }
            $groups[$key]['duration']++;
        }

        return array_values($groups);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function columns($start, int $days, string $scale): array
    {
        if ($scale === 'day') {
            return collect(CarbonPeriod::create($start, $start->copy()->addDays($days - 1)))
                ->values()
                ->map(fn ($date): array => [
                    'label' => $date->format('j'),
                    'short_label' => $date->translatedFormat('D'),
                    'date' => $date->toDateString(),
                    'offset' => $start->diffInDays($date),
                    'duration' => 1,
                    'is_weekend' => $date->isWeekend(),
                ])->all();
        }

        $unit = $scale === 'week' ? 'week' : 'month';
        $columns = [];
        $cursor = $start->copy();

        while ($start->diffInDays($cursor) < $days) {
            $offset = $start->diffInDays($cursor);
            $duration = $unit === 'week'
                ? min(7, $days - $offset)
                : min($cursor->daysInMonth - $cursor->day + 1, $days - $offset);

            $columns[] = [
                'label' => $unit === 'week' ? 'S'.$cursor->isoWeek : $cursor->translatedFormat('M Y'),
                'date' => $cursor->toDateString(),
                'offset' => $offset,
                'duration' => $duration,
            ];
            $cursor->addDays($duration);
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function marker($start, int $days, $date): array
    {
        $offset = $start->diffInDays($date, false);

        return [
            'date' => $date->toDateString(),
            'offset' => $offset,
            'position' => max(0, min(100, (($offset + 0.5) / $days) * 100)),
            'within_horizon' => $offset >= 0 && $offset < $days,
        ];
    }

    /**
     * @return array<int, array{date: string, offset: int, duration: int}>
     */
    private function weekendRanges($start, int $days): array
    {
        return collect(CarbonPeriod::create($start, $start->copy()->addDays($days - 1)))
            ->filter(fn ($date): bool => $date->isWeekend())
            ->map(fn ($date): array => [
                'date' => $date->toDateString(),
                'offset' => $start->diffInDays($date),
                'duration' => 1,
            ])->values()->all();
    }
}
