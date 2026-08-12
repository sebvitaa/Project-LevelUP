<?php

namespace App\Enums;

/**
 * Calidad de modelo con la que se genera la malla.
 *
 * El enum guarda la *intención* (estándar o avanzado) y no el identificador de
 * Gemini, que vive en `config/services.php`. Así cambiar de modelo en Google es
 * tocar el `.env` y no la base de datos: los proyectos ya generados siguen
 * diciendo con qué calidad se hicieron aunque el identificador cambie.
 */
enum AiModel: string
{
    case Standard = 'standard';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Estándar',
            self::Advanced => 'Avanzado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => 'Rápido y suficiente para proyectos acotados.',
            self::Advanced => 'Mallas más detalladas y mejores estimaciones en proyectos complejos.',
        };
    }

    /** Identificador real del modelo en la API de Gemini. */
    public function geminiModel(): string
    {
        return (string) config("services.gemini.models.{$this->value}");
    }

    /** Plan mínimo que habilita este modelo. */
    public function requiredPlan(): SubscriptionPlan
    {
        return match ($this) {
            self::Standard => SubscriptionPlan::Free,
            self::Advanced => SubscriptionPlan::Pro,
        };
    }
}
