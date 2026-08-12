<?php

namespace App\Http\Controllers;

use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Carbon\CarbonInterface;
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

        if ($project->status === ProjectStatus::Draft) {
            return redirect()->route('dashboard');
        }

        $pendingClarifications = $project->status === ProjectStatus::AwaitingInput
            ? $project->pendingClarifications()->get()
            : collect();

        return view('projects.generating', [
            'project' => $project,
            'pendingClarifications' => $pendingClarifications,
            'progress' => $this->progressPayload($project),
        ]);
    }

    /**
     * Estado de la generación, consultado por la vista.
     */
    public function status(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()
            ->json($this->progressPayload($project))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array<string, mixed>
     */
    private function progressPayload(Project $project): array
    {
        $stage = $project->generation_stage;
        [$stepIndex, $message] = $this->stageProgress($project, $stage);
        $lastProgressAt = $project->generation_progressed_at;
        $active = in_array($project->status, [ProjectStatus::Clarifying, ProjectStatus::Generating], true);
        $stalledAfter = max(1, (int) config('levelup.generation_stalled_after', 120));
        $isStalled = $active
            && $lastProgressAt instanceof CarbonInterface
            && now()->diffInSeconds($lastProgressAt) > $stalledAfter;

        return [
            'status' => $project->status->value,
            'stage' => $stage?->value,
            'step_index' => $stepIndex,
            'step_count' => 5,
            'message' => $message,
            'is_terminal' => $project->status->isTerminal(),
            'needs_input' => $project->status->needsUserInput(),
            'started_at' => $project->generation_started_at?->toISOString(),
            'last_progress_at' => $lastProgressAt?->toISOString(),
            'is_stalled' => $isStalled,
            'error' => $project->generation_error,
            'redirect_to' => match ($project->status) {
                ProjectStatus::Ready => route('projects.show', $project),
                ProjectStatus::Draft => route('dashboard'),
                default => null,
            },
        ];
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function stageProgress(Project $project, ?ProjectGenerationStage $stage): array
    {
        if ($project->status === ProjectStatus::AwaitingInput) {
            return [2, 'Responde las preguntas para continuar.'];
        }

        if ($project->status === ProjectStatus::Failed) {
            return [$this->stageStepIndex($stage), 'La generación falló.'];
        }

        if ($project->status === ProjectStatus::Ready) {
            return [5, 'La malla está lista.'];
        }

        return match ($stage) {
            ProjectGenerationStage::Queued => [1, 'En cola'],
            ProjectGenerationStage::AnalyzingBrief => [1, 'Analizando la descripción'],
            ProjectGenerationStage::AwaitingAnswers => [2, 'Esperando respuestas'],
            ProjectGenerationStage::RequestingPlan => [2, 'Solicitando el plan'],
            ProjectGenerationStage::ValidatingPlan => [3, 'Validando el plan'],
            ProjectGenerationStage::CalculatingCpm => [4, 'Calculando la ruta crítica'],
            ProjectGenerationStage::Persisting => [5, 'Guardando la malla'],
            ProjectGenerationStage::Complete => [5, 'Completado'],
            null => [null, $project->status->label()],
        };
    }

    private function stageStepIndex(?ProjectGenerationStage $stage): ?int
    {
        return match ($stage) {
            ProjectGenerationStage::Queued,
            ProjectGenerationStage::AnalyzingBrief => 1,
            ProjectGenerationStage::AwaitingAnswers,
            ProjectGenerationStage::RequestingPlan => 2,
            ProjectGenerationStage::ValidatingPlan => 3,
            ProjectGenerationStage::CalculatingCpm => 4,
            ProjectGenerationStage::Persisting,
            ProjectGenerationStage::Complete => 5,
            null => null,
        };
    }
}
