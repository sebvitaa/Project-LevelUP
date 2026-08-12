<?php

use App\Enums\ProjectGenerationStage;
use App\Models\Project;

it('define los hitos de generación en el orden del flujo', function () {
    expect(ProjectGenerationStage::cases())->toHaveCount(8)
        ->and(ProjectGenerationStage::cases())->toEqual([
            ProjectGenerationStage::Queued,
            ProjectGenerationStage::AnalyzingBrief,
            ProjectGenerationStage::AwaitingAnswers,
            ProjectGenerationStage::RequestingPlan,
            ProjectGenerationStage::ValidatingPlan,
            ProjectGenerationStage::CalculatingCpm,
            ProjectGenerationStage::Persisting,
            ProjectGenerationStage::Complete,
        ]);
});

it('marca solo complete como etapa terminal', function () {
    expect(ProjectGenerationStage::Complete->isTerminal())->toBeTrue()
        ->and(ProjectGenerationStage::AwaitingAnswers->isTerminal())->toBeFalse();
});

it('castea la metadata de generación y comienza en el intento cero', function () {
    $project = Project::make();

    expect($project->generation_attempt)->toBe(0)
        ->and($project->generation_stage)->toBeNull();

    $project->generation_stage = ProjectGenerationStage::RequestingPlan->value;
    $project->charged_generation_attempt = '2';

    expect($project->generation_stage)->toBe(ProjectGenerationStage::RequestingPlan)
        ->and($project->charged_generation_attempt)->toBe(2);
});
