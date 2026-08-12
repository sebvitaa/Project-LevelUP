<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Exceptions\PlanGenerationException;
use App\Models\Project;
use App\Services\Ai\ProjectClarificationGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Analiza el brief antes de iniciar la generación final de la malla.
 */
class GenerateProjectClarifications implements ShouldBeUnique, ShouldQueue
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

    public function handle(ProjectClarificationGenerator $generator): void
    {
        $project = Project::query()->find($this->projectId);

        if ($project === null
            || $project->generation_attempt !== $this->generationAttempt
            || $project->status !== ProjectStatus::Clarifying) {
            return;
        }

        try {
            $awaitsInput = $generator->generate($project, $this->generationAttempt);

            if ($awaitsInput === false) {
                GenerateProjectSchedule::dispatch($this->projectId, $this->generationAttempt)
                    ->afterCommit();
            }
        } catch (PlanGenerationException $e) {
            $this->markFailed($e->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed('Algo se cayó mientras analizábamos el brief. Vuelve a intentarlo.');
    }

    private function markFailed(string $message): void
    {
        Project::query()
            ->whereKey($this->projectId)
            ->where('generation_attempt', $this->generationAttempt)
            ->where('status', ProjectStatus::Clarifying->value)
            ->update([
                'status' => ProjectStatus::Failed->value,
                'generation_error' => $message,
                'generation_progressed_at' => now(),
            ]);
    }
}
