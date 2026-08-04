<?php

namespace App\Enums;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Criterio de orden de las tarjetas del dashboard.
 *
 * Por defecto se ordena por fecha límite: lo que vence antes se lee primero.
 * Los proyectos sin fecha caen al final en vez de encabezar la lista.
 */
enum DashboardSort: string
{
    case Deadline = 'fecha-limite';
    case Progress = 'avance';
    case Name = 'nombre';

    public function label(): string
    {
        return match ($this) {
            self::Deadline => 'Fecha límite',
            self::Progress => 'Avance',
            self::Name => 'Nombre',
        };
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Project>
     */
    public function apply(Collection $projects): Collection
    {
        return match ($this) {
            self::Deadline => $projects->sortBy(
                fn (Project $project): string => $project->deadline?->format('Y-m-d') ?? '9999-12-31'
            )->values(),
            self::Progress => $projects->sortByDesc(
                fn (Project $project): int => $project->completionPercentage()
            )->values(),
            self::Name => $projects->sortBy(
                fn (Project $project): string => mb_strtolower($project->name)
            )->values(),
        };
    }
}
