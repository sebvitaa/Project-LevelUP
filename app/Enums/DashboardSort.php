<?php

namespace App\Enums;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Criterios de orden disponibles para la grilla del dashboard. */
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
                fn (Project $project): string => Str::lower($project->name)
            )->values(),
        };
    }
}
