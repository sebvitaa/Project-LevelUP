<?php

namespace App\Enums;

/**
 * Hitos observables del trabajo de generación de un proyecto.
 */
enum ProjectGenerationStage: string
{
    case Queued = 'queued';
    case AnalyzingBrief = 'analyzing_brief';
    case AwaitingAnswers = 'awaiting_answers';
    case RequestingPlan = 'requesting_plan';
    case ValidatingPlan = 'validating_plan';
    case CalculatingCpm = 'calculating_cpm';
    case Persisting = 'persisting';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::AnalyzingBrief => 'Analizando la descripción',
            self::AwaitingAnswers => 'Esperando respuestas',
            self::RequestingPlan => 'Solicitando el plan',
            self::ValidatingPlan => 'Validando el plan',
            self::CalculatingCpm => 'Calculando la ruta crítica',
            self::Persisting => 'Guardando la malla',
            self::Complete => 'Completado',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Complete;
    }
}
