<?php

namespace App\Enums;

/**
 * Plan de suscripción del usuario.
 *
 * El plan define dos cosas: cuántas generaciones mensuales tiene y a qué
 * modelos de IA puede acceder. Los valores viven acá y no en config porque son
 * reglas de negocio que los tests cubren, no parámetros de despliegue.
 */
enum SubscriptionPlan: string
{
    case Free = 'free';
    case Pro = 'pro';

    /** Días que dura una contratación antes de volver al plan gratuito. */
    public const PRO_PERIOD_DAYS = 30;

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratis',
            self::Pro => 'Pro',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Free => 'Para probar la herramienta y planificar proyectos acotados.',
            self::Pro => 'Para quien planifica seguido y necesita mallas más finas.',
        };
    }

    /** Precio mensual en dólares. */
    public function monthlyPriceUsd(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Pro => 10,
        };
    }

    /** Generaciones incluidas por período. */
    public function monthlyCredits(): int
    {
        return match ($this) {
            self::Free => 20,
            self::Pro => 200,
        };
    }

    /**
     * Modelos de IA habilitados por el plan.
     *
     * @return array<int, AiModel>
     */
    public function models(): array
    {
        return array_values(array_filter(
            AiModel::cases(),
            fn (AiModel $model): bool => $this->allows($model),
        ));
    }

    public function allows(AiModel $model): bool
    {
        return match ($model->requiredPlan()) {
            self::Free => true,
            self::Pro => $this === self::Pro,
        };
    }

    /**
     * Lo que gana el usuario al contratar, para mostrarlo en la pantalla de plan.
     *
     * @return array<int, string>
     */
    public function highlights(): array
    {
        return match ($this) {
            self::Free => [
                self::Free->monthlyCredits().' generaciones al mes',
                'Modelo estándar',
                'Malla CPM, carta Gantt y seguimiento de avance',
            ],
            self::Pro => [
                self::Pro->monthlyCredits().' generaciones al mes',
                'Modelo avanzado, con mallas más detalladas',
                'Todo lo del plan gratis',
            ],
        };
    }
}
