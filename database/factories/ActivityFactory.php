<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => fake()->unique()->randomLetter(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(2),
            'duration_days' => fake()->numberBetween(1, 15),
        ];
    }

    /** Actividad ya resuelta por el CPM y en la ruta crítica. */
    public function critical(int $earlyStart, int $duration): static
    {
        return $this->state(fn (): array => [
            'duration_days' => $duration,
            'early_start' => $earlyStart,
            'early_finish' => $earlyStart + $duration,
            'late_start' => $earlyStart,
            'late_finish' => $earlyStart + $duration,
            'slack' => 0,
            'is_critical' => true,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'completed_at' => now(),
        ]);
    }
}
