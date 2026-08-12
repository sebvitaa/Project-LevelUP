<?php

namespace App\Http\Controllers;

use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectClarificationAnswersRequest;
use App\Jobs\GenerateProjectSchedule;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectClarificationController extends Controller
{
    public function store(
        StoreProjectClarificationAnswersRequest $request,
        Project $project,
    ): RedirectResponse {
        $answers = $request->validated()['answers'];

        DB::transaction(function () use ($answers, $project): void {
            $lockedProject = Project::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProject->status !== ProjectStatus::AwaitingInput) {
                throw ValidationException::withMessages([
                    'answers' => 'Estas preguntas ya no están pendientes.',
                ]);
            }

            $pending = $lockedProject->pendingClarifications()->get();
            $expectedKeys = array_map('strval', $pending->modelKeys());
            $submittedKeys = array_map('strval', array_keys($answers));
            sort($expectedKeys);
            sort($submittedKeys);

            if ($expectedKeys !== $submittedKeys) {
                throw ValidationException::withMessages([
                    'answers' => 'Las preguntas pendientes cambiaron. Recarga la página e inténtalo de nuevo.',
                ]);
            }

            $answeredAt = now();

            foreach ($pending as $clarification) {
                $clarification->forceFill([
                    'answer' => $answers[$clarification->getKey()],
                    'answered_at' => $answeredAt,
                ])->save();
            }

            $lockedProject->forceFill([
                'status' => ProjectStatus::Generating,
                'generation_stage' => ProjectGenerationStage::RequestingPlan,
                'generation_progressed_at' => $answeredAt,
                'generation_error' => null,
            ])->save();

            GenerateProjectSchedule::dispatch(
                $lockedProject->getKey(),
                $lockedProject->generation_attempt,
            )->afterCommit();
        });

        return redirect()->route('projects.generating', $project);
    }
}
