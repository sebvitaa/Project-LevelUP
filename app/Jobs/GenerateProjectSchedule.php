<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Exceptions\PlanGenerationException;
use App\Models\Project;
use App\Services\Ai\ProjectPlanGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Genera la malla del proyecto fuera del ciclo de request.
 */
class GenerateProjectSchedule implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $projectId,
        public int $generationAttempt,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->projectId;
    }

    public function handle(ProjectPlanGenerator $generator): void
    {
        $project = Project::query()
            ->with('user')
            ->find($this->projectId);

        if ($project === null || ! $project->isCurrentGenerationAttempt($this->generationAttempt)) {
            return;
        }

        $user = $project->user;

        if (! $user->hasAiCreditsAvailable()) {
            $this->markFailed(PlanGenerationException::noCreditsLeft());

            return;
        }

        try {
            if (! $generator->generate($project, $this->generationAttempt)) {
                return;
            }
        } catch (PlanGenerationException $e) {
            $this->markFailed($e);

            return;
        }

    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailedMessage('Algo se cayó mientras generábamos la malla. Vuelve a intentarlo.');
    }

    private function markFailed(PlanGenerationException $exception): void
    {
        $this->markFailedMessage($exception->getMessage());
    }

    private function markFailedMessage(string $message): void
    {
        Project::query()
            ->whereKey($this->projectId)
            ->where('generation_attempt', $this->generationAttempt)
            ->where('status', ProjectStatus::Generating->value)
            ->update([
                'status' => ProjectStatus::Failed->value,
                'generation_error' => $message,
                'generation_progressed_at' => now(),
            ]);
    }
}
