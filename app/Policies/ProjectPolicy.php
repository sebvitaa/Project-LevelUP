<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Un proyecto solo lo ve y lo toca su dueño. No hay proyectos compartidos en
 * el MVP; cuando se agreguen, esta clase es el único lugar que cambia.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAiCreditsAvailable();
    }

    public function update(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
}
