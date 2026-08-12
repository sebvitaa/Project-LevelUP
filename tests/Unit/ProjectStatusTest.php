<?php

use App\Enums\ProjectStatus;

it('distingue el estado que espera respuestas del usuario', function () {
    expect(ProjectStatus::AwaitingInput->needsUserInput())->toBeTrue()
        ->and(ProjectStatus::Clarifying->needsUserInput())->toBeFalse()
        ->and(ProjectStatus::AwaitingInput->isTerminal())->toBeFalse()
        ->and(ProjectStatus::Ready->isTerminal())->toBeTrue();
});
