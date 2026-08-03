<?php

use App\Services\Cpm\CpmCalculator;

/**
 * Malla de referencia, la misma que aparece en los mockups.
 *
 * Ruta crítica esperada: A → C → D → F → G → H, 39 días.
 * B y E quedan con 6 días de holgura.
 *
 * @return array<int, array{code: string, duration_days: int, predecessors: array<int, string>}>
 */
function referenceGraph(): array
{
    return [
        ['code' => 'A', 'duration_days' => 5, 'predecessors' => []],
        ['code' => 'B', 'duration_days' => 8, 'predecessors' => ['A']],
        ['code' => 'C', 'duration_days' => 6, 'predecessors' => ['A']],
        ['code' => 'D', 'duration_days' => 12, 'predecessors' => ['C']],
        ['code' => 'E', 'duration_days' => 10, 'predecessors' => ['B']],
        ['code' => 'F', 'duration_days' => 6, 'predecessors' => ['D']],
        ['code' => 'G', 'duration_days' => 7, 'predecessors' => ['E', 'F']],
        ['code' => 'H', 'duration_days' => 3, 'predecessors' => ['G']],
    ];
}

beforeEach(function () {
    $this->cpm = new CpmCalculator;
});

it('calcula los tiempos tempranos con la pasada hacia adelante', function () {
    $schedule = $this->cpm->calculate(referenceGraph());

    expect($schedule['A']->earlyStart)->toBe(0)
        ->and($schedule['A']->earlyFinish)->toBe(5)
        ->and($schedule['C']->earlyStart)->toBe(5)
        ->and($schedule['D']->earlyStart)->toBe(11)
        ->and($schedule['D']->earlyFinish)->toBe(23)
        // G espera a la más lenta de sus dos precedentes: F termina en 29, E en 23.
        ->and($schedule['G']->earlyStart)->toBe(29)
        ->and($schedule['H']->earlyFinish)->toBe(39);
});

it('calcula los tiempos tardíos con la pasada hacia atrás', function () {
    $schedule = $this->cpm->calculate(referenceGraph());

    expect($schedule['H']->lateFinish)->toBe(39)
        ->and($schedule['H']->lateStart)->toBe(36)
        ->and($schedule['B']->lateStart)->toBe(11)
        ->and($schedule['E']->lateStart)->toBe(19)
        ->and($schedule['A']->lateStart)->toBe(0);
});

it('marca como crítica solo la cadena sin holgura', function () {
    $schedule = $this->cpm->calculate(referenceGraph());

    expect($this->cpm->criticalPath($schedule))->toBe(['A', 'C', 'D', 'F', 'G', 'H'])
        ->and($schedule['B']->isCritical)->toBeFalse()
        ->and($schedule['E']->isCritical)->toBeFalse();
});

it('asigna 6 días de holgura a la rama no crítica', function () {
    $schedule = $this->cpm->calculate(referenceGraph());

    expect($schedule['B']->slack)->toBe(6)
        ->and($schedule['E']->slack)->toBe(6)
        ->and($schedule['A']->slack)->toBe(0)
        ->and($schedule['G']->slack)->toBe(0);
});

it('devuelve la duración total del proyecto', function () {
    $schedule = $this->cpm->calculate(referenceGraph());

    expect($this->cpm->totalDuration($schedule))->toBe(39);
});

it('ubica cada actividad en una columna según su profundidad en el grafo', function () {
    $schedule = $this->cpm->calculate(referenceGraph());

    expect($schedule['A']->gridColumn)->toBe(0)
        ->and($schedule['B']->gridColumn)->toBe(1)
        ->and($schedule['C']->gridColumn)->toBe(1)
        ->and($schedule['H']->gridColumn)->toBe(5);
});

it('pone las actividades críticas en la fila superior de su columna', function () {
    $schedule = $this->cpm->calculate(referenceGraph());

    expect($schedule['C']->gridRow)->toBe(0)
        ->and($schedule['B']->gridRow)->toBe(1)
        ->and($schedule['D']->gridRow)->toBe(0)
        ->and($schedule['E']->gridRow)->toBe(1);
});

it('resuelve una malla de una sola actividad', function () {
    $schedule = $this->cpm->calculate([
        ['code' => 'A', 'duration_days' => 4, 'predecessors' => []],
    ]);

    expect($schedule['A']->isCritical)->toBeTrue()
        ->and($this->cpm->totalDuration($schedule))->toBe(4);
});

it('resuelve ramas paralelas que nunca se juntan', function () {
    $schedule = $this->cpm->calculate([
        ['code' => 'A', 'duration_days' => 3, 'predecessors' => []],
        ['code' => 'B', 'duration_days' => 9, 'predecessors' => []],
    ]);

    // La más larga define el proyecto; la más corta hereda su holgura.
    expect($schedule['B']->isCritical)->toBeTrue()
        ->and($schedule['A']->isCritical)->toBeFalse()
        ->and($schedule['A']->slack)->toBe(6);
});

it('rechaza una dependencia circular', function () {
    $this->cpm->calculate([
        ['code' => 'A', 'duration_days' => 2, 'predecessors' => ['B']],
        ['code' => 'B', 'duration_days' => 2, 'predecessors' => ['A']],
    ]);
})->throws(InvalidArgumentException::class, 'dependencia circular');

it('rechaza un precedente que no existe en la malla', function () {
    $this->cpm->calculate([
        ['code' => 'A', 'duration_days' => 2, 'predecessors' => ['Z']],
    ]);
})->throws(InvalidArgumentException::class, 'no existe en la malla');

it('rechaza códigos de actividad repetidos', function () {
    $this->cpm->calculate([
        ['code' => 'A', 'duration_days' => 2, 'predecessors' => []],
        ['code' => 'A', 'duration_days' => 3, 'predecessors' => []],
    ]);
})->throws(InvalidArgumentException::class, 'está repetida');
