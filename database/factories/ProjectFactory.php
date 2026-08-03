<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-2 months', '+1 month');

        return [
            'user_id' => User::factory(),
            'name' => fake()->catchPhrase(),
            'type' => fake()->randomElement(ProjectType::cases()),
            'prompt' => fake()->paragraph(4),
            'starts_on' => $startsOn,
            'deadline' => fake()->dateTimeBetween($startsOn, '+6 months'),
            'team_size' => fake()->numberBetween(2, 12),
            'status' => ProjectStatus::Draft,
        ];
    }

    /** Proyecto recién creado, todavía sin malla. */
    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Draft,
            'total_duration_days' => null,
            'generated_at' => null,
        ]);
    }

    /** Job en cola: la pantalla 05 está haciendo polling. */
    public function generating(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Generating,
            'generation_error' => null,
        ]);
    }

    /** Malla generada y CPM resuelto. */
    public function ready(int $totalDurationDays = 39): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Ready,
            'total_duration_days' => $totalDurationDays,
            'generated_at' => now(),
            'generation_error' => null,
        ]);
    }

    /** La generación falló; el usuario puede reintentar. */
    public function failed(string $error = 'La IA devolvió un plan que no pudimos leer.'): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Failed,
            'generation_error' => $error,
        ]);
    }
}
