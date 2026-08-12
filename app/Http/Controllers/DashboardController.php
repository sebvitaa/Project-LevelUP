<?php

namespace App\Http\Controllers;

use App\Enums\DashboardFilter;
use App\Enums\DashboardSort;
use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Pantalla 02 — dashboard de proyectos, avance y próximas entregas. */
class DashboardController extends Controller
{
    private const TREND_WEEKS = 7;

    public function __invoke(Request $request): View
    {
        $filter = DashboardFilter::tryFrom((string) $request->query('filtro')) ?? DashboardFilter::All;
        $sort = DashboardSort::tryFrom((string) $request->query('orden')) ?? DashboardSort::Deadline;
        $search = $request->string('q')->trim()->limit(120)->toString();
        $projects = $this->projectsWithCounts($request->user()->getKey());
        $visibleProjects = $this->applySearch($projects, $request, $search);

        return view('dashboard.index', [
            'projects' => $sort->apply($filter->apply($visibleProjects)),
            'totalProjects' => $projects->count(),
            'summary' => $this->summarize($projects),
            'filter' => $filter,
            'sort' => $sort,
            'search' => $search,
        ]);
    }

    /**
     * @return Collection<int, Project>
     */
    private function projectsWithCounts(int $userId): Collection
    {
        return Project::query()
            ->where('user_id', $userId)
            ->latest()
            ->withCount([
                'activities',
                'activities as completed_activities_count' => fn (Builder $query) => $query->whereNotNull('completed_at'),
                'activities as critical_activities_count' => fn (Builder $query) => $query->where('is_critical', true),
            ])
            ->get();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Project>
     */
    private function applySearch(Collection $projects, Request $request, string $search): Collection
    {
        if ($search === '') {
            return $projects;
        }

        $matchingIds = Project::query()
            ->where('user_id', $request->user()->getKey())
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('activities', function (Builder $activities) use ($search): void {
                        $activities->where(function (Builder $fields) use ($search): void {
                            $fields->where('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                    });
            })
            ->pluck('id');

        return $projects->whereIn('id', $matchingIds)->values();
    }

    /**
     * Métricas de la fila superior del dashboard.
     *
     * @param  Collection<int, Project>  $projects
     * @return array{
     *     average_completion: int, completion_delta: int, trend: array<int, int>,
     *     completed_activities: int, total_activities: int, completed_this_week: int,
     *     critical_total: int, critical_overdue: int, critical_in_progress: int,
     *     next_deadline: ?Project, days_until_deadline: ?int
     * }
     */
    private function summarize(Collection $projects): array
    {
        $projectIds = $projects->pluck('id')->all();
        $totalActivities = (int) $projects->sum('activities_count');
        $completedActivities = (int) $projects->sum('completed_activities_count');
        $averageCompletion = $totalActivities === 0
            ? 0
            : (int) round($completedActivities / $totalActivities * 100);
        $completions = $this->completionDates($projectIds);
        $trend = $this->completionTrend($completions, $totalActivities);
        $criticalBreakdown = $this->criticalBreakdown($projectIds);
        $nextDeadline = $this->nextDeadline($projects);

        return [
            'average_completion' => $averageCompletion,
            'completion_delta' => $averageCompletion - ($trend[0] ?? 0),
            'trend' => $trend,
            'completed_activities' => $completedActivities,
            'total_activities' => $totalActivities,
            'completed_this_week' => $completions->filter(
                fn (Carbon $date): bool => $date->greaterThanOrEqualTo(now()->subWeek())
            )->count(),
            'critical_total' => $criticalBreakdown['total'],
            'critical_overdue' => $criticalBreakdown['overdue'],
            'critical_in_progress' => $criticalBreakdown['in_progress'],
            'next_deadline' => $nextDeadline,
            'days_until_deadline' => $nextDeadline === null
                ? null
                : (int) now()->startOfDay()->diffInDays($nextDeadline->deadline, false),
        ];
    }

    /**
     * @param  array<int, int>  $projectIds
     * @return Collection<int, Carbon>
     */
    private function completionDates(array $projectIds): Collection
    {
        if ($projectIds === []) {
            return collect();
        }

        return Activity::query()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('completed_at')
            ->get(['completed_at'])
            ->pluck('completed_at');
    }

    /**
     * @param  Collection<int, Carbon>  $completions
     * @return array<int, int>
     */
    private function completionTrend(Collection $completions, int $totalActivities): array
    {
        if ($totalActivities === 0) {
            return array_fill(0, self::TREND_WEEKS, 0);
        }

        $points = [];

        for ($weeksAgo = self::TREND_WEEKS - 1; $weeksAgo >= 0; $weeksAgo--) {
            $checkpoint = now()->subWeeks($weeksAgo)->endOfDay();
            $completedByThen = $completions->filter(
                fn (Carbon $date): bool => $date->lessThanOrEqualTo($checkpoint)
            )->count();

            $points[] = (int) round($completedByThen / $totalActivities * 100);
        }

        return $points;
    }

    /**
     * @param  array<int, int>  $projectIds
     * @return array{total: int, overdue: int, in_progress: int}
     */
    private function criticalBreakdown(array $projectIds): array
    {
        if ($projectIds === []) {
            return ['total' => 0, 'overdue' => 0, 'in_progress' => 0];
        }

        $critical = Activity::query()
            ->whereIn('project_id', $projectIds)
            ->where('is_critical', true)
            ->whereNull('completed_at')
            ->with('project:id,starts_on')
            ->get();
        $overdue = $critical->filter(
            fn (Activity $activity): bool => $activity->finishDate()?->isPast() ?? false
        )->count();

        return [
            'total' => $critical->count(),
            'overdue' => $overdue,
            'in_progress' => $critical->count() - $overdue,
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function nextDeadline(Collection $projects): ?Project
    {
        return $projects
            ->filter(fn (Project $project): bool => $project->status === ProjectStatus::Ready
                && $project->deadline !== null
                && ! $project->isCompleted())
            ->sortBy(fn (Project $project): string => $project->deadline->format('Y-m-d'))
            ->first();
    }
}
