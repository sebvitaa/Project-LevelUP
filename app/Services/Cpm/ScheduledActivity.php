<?php

namespace App\Services\Cpm;

/**
 * Resultado del cálculo CPM para una actividad.
 *
 * Es un objeto de solo lectura: el calculador no toca la base de datos, así
 * puede probarse sin migraciones y reutilizarse para simular escenarios.
 */
final readonly class ScheduledActivity
{
    public function __construct(
        public string $code,
        public int $durationDays,
        public int $earlyStart,
        public int $earlyFinish,
        public int $lateStart,
        public int $lateFinish,
        public int $slack,
        public bool $isCritical,
        public int $gridColumn,
        public int $gridRow,
    ) {}

    /**
     * @return array{
     *     early_start: int, early_finish: int, late_start: int, late_finish: int,
     *     slack: int, is_critical: bool, grid_column: int, grid_row: int
     * }
     */
    public function toAttributes(): array
    {
        return [
            'early_start' => $this->earlyStart,
            'early_finish' => $this->earlyFinish,
            'late_start' => $this->lateStart,
            'late_finish' => $this->lateFinish,
            'slack' => $this->slack,
            'is_critical' => $this->isCritical,
            'grid_column' => $this->gridColumn,
            'grid_row' => $this->gridRow,
        ];
    }
}
