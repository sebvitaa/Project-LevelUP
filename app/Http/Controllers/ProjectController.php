<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Jobs\GenerateProjectSchedule;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Crea el proyecto y encola la generación. Salida de la pantalla 04.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create([
            ...$request->validated(),
            'status' => ProjectStatus::Generating,
        ]);

        $request->session()->forget('project_wizard.type');

        GenerateProjectSchedule::dispatch($project);

        return redirect()->route('projects.generating', $project);
    }

    /**
     * Pantalla 06 — malla CPM con las actividades y sus descripciones.
     */
    public function show(Request $request, Project $project): View|RedirectResponse
    {
        $this->authorize('view', $project);

        if ($project->status !== ProjectStatus::Ready) {
            return redirect()->route('projects.generating', $project);
        }

        $project->load(['activities.predecessors', 'activities.successors']);

        // El padre ya está en memoria: se lo pasamos a los hijos para que
        // Activity::startDate() no gatille una consulta por cada nodo.
        $project->activities->each->setRelation('project', $project);

        // ?activity=D deja seleccionada esa actividad al volver desde un nodo.
        $selected = $project->activities->firstWhere('code', $request->query('activity'))
            ?? $project->activities->firstWhere('is_critical', true)
            ?? $project->activities->first();

        return view('projects.show', [
            'project' => $project,
            'selected' => $selected,
        ]);
    }

    /**
     * Reintenta una generación fallida sin volver a pedir el prompt.
     */
    public function regenerate(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->forceFill([
            'status' => ProjectStatus::Generating,
            'generation_error' => null,
        ])->save();

        GenerateProjectSchedule::dispatch($project);

        return redirect()->route('projects.generating', $project);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('dashboard')->with('status', 'Proyecto eliminado.');
    }
}
