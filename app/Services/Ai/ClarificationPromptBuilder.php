<?php

namespace App\Services\Ai;

use App\Models\Project;

/**
 * Arma las instrucciones para decidir si el brief necesita una aclaración.
 */
class ClarificationPromptBuilder
{
    public function systemInstruction(Project $project): string
    {
        return <<<PROMPT
        Eres un analista de briefs de proyectos. Decide si falta información que pueda cambiar
        las actividades, duraciones o dependencias del cronograma.

        {$project->type->domainHint()}

        Reglas obligatorias:
        - Haz como máximo una ronda de aclaración con entre 1 y 3 preguntas.
        - Pregunta solo por información realmente necesaria para planificar el trabajo.
        - No preguntes por nombre, tipo, fecha inicial, deadline ni tamaño del equipo: ya están dados.
        - Si el brief es suficientemente específico, responde needs_clarification=false y questions=[].
        - Usa input_type=text para respuestas abiertas o select para una elección acotada.
        - Para select entrega entre 2 y 8 opciones cortas, únicas y mutuamente distinguibles.
        - Escribe en español y no incluyas texto fuera del JSON solicitado.
        PROMPT;
    }

    public function userPrompt(Project $project): string
    {
        $context = [
            'Nombre del proyecto: '.$project->name,
            'Tipo de proyecto: '.$project->type->label(),
            'Fecha de inicio: '.$project->starts_on->format('d-m-Y'),
        ];

        if ($project->deadline !== null) {
            $context[] = 'Fecha límite deseada: '.$project->deadline->format('d-m-Y');
        }

        if ($project->team_size !== null) {
            $context[] = 'Tamaño del equipo: '.$project->team_size.' personas';
        }

        return implode("\n", [
            implode("\n", $context),
            '',
            'Descripción del proyecto:',
            $project->prompt,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'needs_clarification' => ['type' => 'BOOLEAN'],
                'questions' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'key' => ['type' => 'STRING'],
                            'question' => ['type' => 'STRING'],
                            'rationale' => ['type' => 'STRING'],
                            'input_type' => [
                                'type' => 'STRING',
                                'enum' => ['text', 'select'],
                            ],
                            'options' => [
                                'type' => 'ARRAY',
                                'items' => ['type' => 'STRING'],
                            ],
                        ],
                        'required' => ['key', 'question', 'rationale', 'input_type', 'options'],
                    ],
                ],
            ],
            'required' => ['needs_clarification', 'questions'],
        ];
    }
}
