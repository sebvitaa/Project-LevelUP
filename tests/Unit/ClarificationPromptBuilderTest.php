<?php

use App\Enums\ProjectType;
use App\Models\Project;
use App\Services\Ai\ClarificationPromptBuilder;

it('distingue preguntas abiertas y de selección en el contrato del prompt', function () {
    $project = Project::make([
        'type' => ProjectType::Software,
        'name' => 'Proyecto de prueba',
        'starts_on' => '2026-01-01',
        'prompt' => 'Crear una aplicación.',
    ]);

    $instruction = (new ClarificationPromptBuilder)->systemInstruction($project);

    expect($instruction)
        ->toContain('Una pregunta text')
        ->toContain('options como lista vacía []')
        ->toContain('Una pregunta select')
        ->toContain('entre 2 y 8 opciones');
});
