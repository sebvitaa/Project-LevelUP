<?php

namespace App\Enums;

/**
 * Tipo de proyecto elegido en el paso 1 del asistente.
 *
 * El tipo condiciona el prompt de sistema que se envía a Gemini: cambia el
 * vocabulario, las actividades sugeridas y el rango esperado de actividades.
 */
enum ProjectType: string
{
    case Software = 'software';
    case Construction = 'construction';
    case Event = 'event';
    case Research = 'research';
    case Marketing = 'marketing';
    case Blank = 'blank';

    public function label(): string
    {
        return match ($this) {
            self::Software => 'Desarrollo de software',
            self::Construction => 'Construcción y obra',
            self::Event => 'Evento o lanzamiento',
            self::Research => 'Investigación o tesis',
            self::Marketing => 'Marketing y campaña',
            self::Blank => 'Desde cero',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Software => 'Sprints, releases, QA, despliegue y dependencias técnicas.',
            self::Construction => 'Partidas, permisos, faenas y precedencias físicas.',
            self::Event => 'Producción, proveedores, difusión y montaje con fecha fija.',
            self::Research => 'Etapas metodológicas, revisiones y entregas académicas.',
            self::Marketing => 'Creatividad, pauta, piezas y medición por canal.',
            self::Blank => 'Sin plantilla. La IA infiere todo desde tu descripción.',
        };
    }

    /**
     * Rango de actividades que se le pide generar a la IA.
     *
     * @return array{0: int, 1: int}
     */
    public function activityRange(): array
    {
        return match ($this) {
            self::Software => [8, 20],
            self::Construction => [15, 40],
            self::Event => [7, 18],
            self::Research => [6, 14],
            self::Marketing => [8, 16],
            self::Blank => [6, 25],
        };
    }

    /**
     * Contexto de dominio que se inyecta en el prompt de sistema de Gemini.
     */
    public function domainHint(): string
    {
        return match ($this) {
            self::Software => 'Es un proyecto de desarrollo de software. Considera levantamiento de requerimientos, diseño, arquitectura, desarrollo backend y frontend, integraciones, QA y despliegue.',
            self::Construction => 'Es un proyecto de construcción. Considera permisos, movimiento de tierra, obra gruesa, terminaciones e inspecciones, respetando precedencias físicas.',
            self::Event => 'Es un evento con fecha fija. Considera producción, contratación de proveedores, difusión, montaje y desmontaje.',
            self::Research => 'Es un proyecto de investigación. Considera revisión bibliográfica, diseño metodológico, recolección y análisis de datos, redacción y revisiones.',
            self::Marketing => 'Es una campaña de marketing. Considera estrategia, producción creativa, pauta, publicación y medición.',
            self::Blank => 'No hay plantilla de dominio. Infiere las fases desde la descripción del usuario.',
        };
    }
}
