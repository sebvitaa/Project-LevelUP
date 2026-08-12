<?php

namespace App\Http\Controllers;

use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Jobs\GenerateProjectClarifications;
use App\Jobs\GenerateProjectSchedule;
use App\Models\Project;
use App\Services\Gantt\GanttTimelineBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Crea el proyecto y encola la generación. Salida de la pantalla 04.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(function () use ($request): Project {
            $project = $request->user()->projects()->create([
                ...$request->validated(),
                'status' => ProjectStatus::Clarifying,
            ]);

            $project->forceFill([
                'generation_stage' => ProjectGenerationStage::AnalyzingBrief,
                'generation_attempt' => 1,
                'generation_started_at' => now(),
                'generation_progressed_at' => now(),
            ])->save();

            GenerateProjectClarifications::dispatch($project->getKey(), $project->generation_attempt)
                ->afterCommit();

            return $project;
        });

        $request->session()->forget('project_wizard.type');

        return redirect()->route('projects.generating', $project);
    }

    /**
     * Pantalla 06 — malla CPM con las actividades y sus descripciones.
     */
    public function show(Request $request, Project $project, GanttTimelineBuilder $gantt): View|RedirectResponse
    {
        $this->authorize('view', $project);

        if ($project->status !== ProjectStatus::Ready) {
            return redirect()->route('projects.generating', $project);
        }

        $project->load(['activities.predecessors', 'activities.successors']);

        // El padre ya está en memoria: se lo pasamos a los hijos para que
        // Activity::startDate() no gatille una consulta por cada nodo.
        $project->activities->each->setRelation('project', $project);

        $view = in_array($request->query('view'), ['network', 'gantt'], true)
            ? $request->query('view')
            : 'network';

        // ?activity=D deja seleccionada esa actividad al volver desde un nodo.
        $selected = $project->activities->firstWhere('code', $request->query('activity'))
            ?? $project->activities->firstWhere('is_critical', true)
            ?? $project->activities->first();

        return view('projects.show', [
            'project' => $project,
            'selected' => $selected,
            'view' => $view,
            'timeline' => $view === 'gantt' ? $gantt->build($project, $project->activities) : null,
        ]);
    }

    /**
     * Reintenta una generación fallida sin volver a pedir el prompt.
     */
    public function regenerate(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        DB::transaction(function () use ($project): void {
            $lockedProject = Project::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProject->status !== ProjectStatus::Failed) {
                return;
            }

            $attempt = $lockedProject->generation_attempt + 1;
            $answeredClarifications = $lockedProject->clarifications()
                ->where('generation_attempt', $lockedProject->generation_attempt)
                ->whereNotNull('answered_at')
                ->get();

            $hasAnswers = $answeredClarifications->isNotEmpty();

            $lockedProject->forceFill([
                'status' => $hasAnswers ? ProjectStatus::Generating : ProjectStatus::Clarifying,
                'generation_stage' => $hasAnswers
                    ? ProjectGenerationStage::RequestingPlan
                    : ProjectGenerationStage::AnalyzingBrief,
                'generation_attempt' => $attempt,
                'generation_error' => null,
                'generation_started_at' => now(),
                'generation_progressed_at' => now(),
            ])->save();

            if ($hasAnswers) {
                $lockedProject->clarifications()->createMany($answeredClarifications->map(
                    fn ($clarification): array => [
                        'round' => $clarification->round,
                        'generation_attempt' => $attempt,
                        'key' => $clarification->key,
                        'question' => $clarification->question,
                        'rationale' => $clarification->rationale,
                        'input_type' => $clarification->input_type,
                        'options' => $clarification->options,
                        'answer' => $clarification->answer,
                        'answered_at' => $clarification->answered_at,
                    ],
                )->all());

                GenerateProjectSchedule::dispatch($lockedProject->getKey(), $attempt)
                    ->afterCommit();
            } else {
                GenerateProjectClarifications::dispatch($lockedProject->getKey(), $attempt)
                    ->afterCommit();
            }
        });

        $project->refresh();

        if ($project->status === ProjectStatus::Ready) {
            return redirect()->route('projects.show', $project);
        }

        return redirect()->route('projects.generating', $project);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('dashboard')->with('status', 'Proyecto eliminado.');
    }
}
