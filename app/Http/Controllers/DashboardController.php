<?php

namespace App\Http\Controllers;

use App\Enums\DashboardFilter;
use App\Enums\DashboardSort;
use App\Models\Activity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Pantalla 02 — dashboard de proyectos y completaciones.
 *
 * Todo lo que muestra sale de la base: no hay cifras decorativas. La fila de
 * KPI resume siempre la cartera completa, aunque la grilla de abajo esté
 * filtrada, porque el resumen responde "¿cómo voy?" y no "¿qué estoy mirando?".
 */
class DashboardController extends Controller
{
    /** Semanas que abarca la miniatura de tendencia. */
    private const TREND_WEEKS = 7;

    public function __invoke(Request $request): View
    {
        $filter = DashboardFilter::tryFrom((string) $request->query('filtro')) ?? DashboardFilter::All;
        $sort = DashboardSort::tryFrom((string) $request->query('orden')) ?? DashboardSort::Deadline;

        $projects = $this->projectsWithCounts($request->user()->id);

        return view('dashboard.index', [
            'projects' => $sort->apply($filter->apply($projects)),
            'totalProjects' => $projects->count(),
            'summary' => $this->summarize($projects),
            'filter' => $filter,
            'sort' => $sort,
        ]);
    }

    /**
     * Los proyectos del usuario con sus tres conteos de actividades, para que
     * ni la grilla ni los KPI vuelvan a consultar por cada tarjeta.
     *
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
     * Las cuatro tarjetas de la fila de KPI.
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
            // La tendencia arranca hace 7 semanas: el delta es contra ese punto.
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
     * Fechas en que se completó cada actividad de la cartera.
     *
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
            ->pluck('completed_at');
    }

    /**
     * Serie semanal del % de avance acumulado, para la miniatura de tendencia.
     *
     * Se calcula sobre el total actual de actividades: el gráfico responde
     * "cuánto de lo que hay que hacer ya estaba hecho en cada momento".
     *
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
     * Actividades críticas pendientes, separadas entre atrasadas y en curso.
     *
     * Atrasada = su fecha de término proyectada ya pasó y sigue sin cerrarse.
     *
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
     * El proyecto vivo cuya fecha límite llega antes.
     *
     * @param  Collection<int, Project>  $projects
     */
    private function nextDeadline(Collection $projects): ?Project
    {
        return $projects
            ->filter(fn (Project $project): bool => $project->deadline !== null && ! $project->isCompleted())
            ->sortBy(fn (Project $project): string => $project->deadline->format('Y-m-d'))
            ->first();
    }
}
