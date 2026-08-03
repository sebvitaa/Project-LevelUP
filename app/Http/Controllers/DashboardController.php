<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Pantalla 02 — dashboard de proyectos y completaciones. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $projects = $request->user()
            ->projects()
            ->withCount([
                'activities',
                'activities as completed_activities_count' => fn ($query) => $query->whereNotNull('completed_at'),
                'activities as critical_activities_count' => fn ($query) => $query->where('is_critical', true),
            ])
            ->get();

        return view('dashboard.index', [
            'projects' => $projects,
            'summary' => $this->summarize($projects),
        ]);
    }

    /**
     * Métricas de la fila superior del dashboard.
     *
     * @param  Collection<int, Project>  $projects
     * @return array{
     *     average_completion: int, completed_activities: int, total_activities: int,
     *     critical_activities: int, next_deadline: ?Project
     * }
     */
    private function summarize($projects): array
    {
        $totalActivities = (int) $projects->sum('activities_count');
        $completedActivities = (int) $projects->sum('completed_activities_count');

        return [
            'average_completion' => $totalActivities === 0
                ? 0
                : (int) round($completedActivities / $totalActivities * 100),
            'completed_activities' => $completedActivities,
            'total_activities' => $totalActivities,
            'critical_activities' => (int) $projects->sum('critical_activities_count'),
            'next_deadline' => $projects->whereNotNull('deadline')->sortBy('deadline')->first(),
        ];
    }
}
