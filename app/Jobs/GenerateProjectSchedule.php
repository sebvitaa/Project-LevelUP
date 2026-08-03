<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Exceptions\PlanGenerationException;
use App\Models\Project;
use App\Services\Ai\ProjectPlanGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Genera la malla del proyecto fuera del ciclo de request.
 *
 * La llamada a Gemini tarda ~30 s, demasiado para bloquear una respuesta HTTP.
 * La pantalla 05 muestra el avance haciendo polling a projects.status mientras
 * este job corre.
 */
class GenerateProjectSchedule implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public Project $project) {}

    public function handle(ProjectPlanGenerator $generator): void
    {
        $user = $this->project->user;

        if (! $user->hasAiCreditsAvailable()) {
            $this->markFailed(PlanGenerationException::noCreditsLeft());

            return;
        }

        try {
            $generator->generate($this->project);
        } catch (PlanGenerationException $e) {
            $this->markFailed($e);

            return;
        }

        // El crédito se descuenta solo si la generación llegó a buen puerto.
        $user->consumeAiCredit();
    }

    public function failed(?Throwable $exception): void
    {
        $this->project->forceFill([
            'status' => ProjectStatus::Failed,
            'generation_error' => 'Algo se cayó mientras generábamos la malla. Vuelve a intentarlo.',
        ])->save();
    }

    private function markFailed(PlanGenerationException $exception): void
    {
        $this->project->forceFill([
            'status' => ProjectStatus::Failed,
            'generation_error' => $exception->getMessage(),
        ])->save();
    }
}
