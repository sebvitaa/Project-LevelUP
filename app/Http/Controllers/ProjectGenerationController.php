<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla 05 — espera mientras el job arma la malla.
 *
 * La vista hace polling a `status` cada 2 segundos y redirige sola cuando el
 * proyecto llega a un estado terminal.
 */
class ProjectGenerationController extends Controller
{
    public function show(Request $request, Project $project): View|RedirectResponse
    {
        $this->authorize('view', $project);

        if ($project->status === ProjectStatus::Ready) {
            return redirect()->route('projects.show', $project);
        }

        return view('projects.generating', ['project' => $project]);
    }

    /**
     * Estado de la generación, consultado por la vista.
     */
    public function status(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'status' => $project->status->value,
            'is_terminal' => $project->status->isTerminal(),
            'error' => $project->generation_error,
            'redirect_to' => $project->status === ProjectStatus::Ready
                ? route('projects.show', $project)
                : null,
        ]);
    }
}
