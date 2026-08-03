<?php

namespace App\Exceptions;

use Exception;

/**
 * La generación del plan falló en algún punto entre la llamada a Gemini y la
 * validación de la malla. El mensaje se muestra tal cual al usuario, así que
 * debe explicar qué pasó y qué puede hacer.
 */
class PlanGenerationException extends Exception
{
    public static function apiUnavailable(string $reason): self
    {
        return new self("No pudimos contactar al servicio de IA: {$reason}. Vuelve a intentarlo en unos minutos.");
    }

    public static function invalidResponse(string $reason): self
    {
        return new self("La IA devolvió un plan que no pudimos leer: {$reason}. Prueba describiendo el proyecto con más detalle.");
    }

    public static function invalidGraph(string $reason): self
    {
        return new self("El plan generado no forma una malla válida: {$reason}. Vuelve a generarlo o ajusta la descripción.");
    }

    public static function noCreditsLeft(): self
    {
        return new self('Se acabaron tus consultas del mes. Se renuevan el día 1 o puedes subir de plan.');
    }
}
