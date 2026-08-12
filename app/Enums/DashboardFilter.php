<?php

namespace App\Enums;

use App\Models\Project;
use Illuminate\Support\Collection;

/** Filtros disponibles para la grilla del dashboard. */
enum DashboardFilter: string
{
    case All = 'todos';
    case AtRisk = 'riesgo';
    case Completed = 'completados';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Todos',
            self::AtRisk => 'En riesgo',
            self::Completed => 'Completados',
        };
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Project>
     */
    public function apply(Collection $projects): Collection
    {
        return match ($this) {
            self::All => $projects->values(),
            self::AtRisk => $projects->filter(
                fn (Project $project): bool => $project->isAtRisk()
            )->values(),
            self::Completed => $projects->filter(
                fn (Project $project): bool => $project->isCompleted()
            )->values(),
        };
    }
}
