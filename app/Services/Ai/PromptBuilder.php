<?php

namespace App\Services\Ai;

use App\Models\Project;

/**
 * Arma el prompt que se le manda a Gemini.
 *
 * El CPM no se le pide a la IA: la IA solo identifica actividades, duraciones
 * y precedencias. Los tiempos ES/EF/LS/LF los calcula CpmCalculator en el
 * servidor, porque un modelo de lenguaje no es una herramienta de cálculo
 * confiable y el resultado tiene que ser reproducible.
 */
class PromptBuilder
{
    /**
     * Instrucciones de sistema: rol, restricciones y contexto de dominio.
     */
    public function systemInstruction(Project $project): string
    {
        [$min, $max] = $project->type->activityRange();

        return <<<PROMPT
        Eres un planificador de proyectos experto en el método de la ruta crítica (CPM).

        {$project->type->domainHint()}

        Descompón el proyecto que te describa el usuario en entre {$min} y {$max} actividades.

        Reglas obligatorias:
        - Cada actividad lleva un código correlativo de una letra mayúscula: A, B, C, …
        - La duración va en días corridos, como número entero mayor que cero.
        - Los precedentes son códigos de actividades ya definidas en la misma lista.
        - Al menos una actividad debe partir sin precedentes.
        - No generes dependencias circulares: el grafo debe ser acíclico.
        - No calcules fechas, holguras ni ruta crítica; eso lo hace el sistema.
        - Escribe nombres y descripciones en español, en un lenguaje concreto y accionable.
        - La descripción de cada actividad explica qué hay que hacer, en 1 o 2 frases.

        Responde únicamente con el JSON pedido, sin texto adicional.
        PROMPT;
    }

    /**
     * Mensaje del usuario: su descripción más el contexto del asistente.
     */
    public function userPrompt(Project $project): string
    {
        $lines = [
            'Nombre del proyecto: '.$project->name,
            'Tipo de proyecto: '.$project->type->label(),
            'Fecha de inicio: '.$project->starts_on->format('d-m-Y'),
        ];

        if ($project->deadline !== null) {
            $lines[] = 'Fecha límite deseada: '.$project->deadline->format('d-m-Y');
        }

        if ($project->team_size !== null) {
            $lines[] = 'Tamaño del equipo: '.$project->team_size.' personas';
        }

        $context = implode("\n", $lines);

        return <<<PROMPT
        {$context}

        Descripción del proyecto:
        {$project->prompt}
        PROMPT;
    }

    /**
     * Esquema de respuesta estructurada. Gemini lo respeta cuando se envía en
     * `generationConfig.responseSchema`, así se evita parsear texto libre.
     *
     * @return array<string, mixed>
     */
    public function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'activities' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'code' => ['type' => 'STRING'],
                            'name' => ['type' => 'STRING'],
                            'description' => ['type' => 'STRING'],
                            'duration_days' => ['type' => 'INTEGER'],
                            'predecessors' => [
                                'type' => 'ARRAY',
                                'items' => ['type' => 'STRING'],
                            ],
                        ],
                        'required' => ['code', 'name', 'description', 'duration_days', 'predecessors'],
                    ],
                ],
            ],
            'required' => ['activities'],
        ];
    }
}
