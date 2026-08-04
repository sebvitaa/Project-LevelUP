<?php

namespace App\Enums;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Pestañas de la sección "Proyectos activos" del dashboard.
 *
 * El filtrado ocurre en memoria y no en la consulta porque la fila de KPI
 * siempre resume *todos* los proyectos del usuario: filtrar en SQL obligaría a
 * una segunda consulta para el resumen. Un usuario del MVP tiene decenas de
 * proyectos, no miles.
 */
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
            self::All => $projects,
            self::AtRisk => $projects->filter(
                fn (Project $project): bool => $project->isAtRisk()
            )->values(),
            self::Completed => $projects->filter(
                fn (Project $project): bool => $project->completionPercentage() === 100
            )->values(),
        };
    }
}
