<?php

namespace Database\Factories;

use App\Enums\ProjectGenerationStage;
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
            'generation_stage' => null,
            'generation_attempt' => 0,
            'charged_generation_attempt' => null,
            'generation_started_at' => null,
            'generation_progressed_at' => null,
        ];
    }

    /** Proyecto recién creado, todavía sin malla. */
    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Draft,
            'total_duration_days' => null,
            'generated_at' => null,
            'generation_stage' => null,
            'generation_attempt' => 0,
            'charged_generation_attempt' => null,
            'generation_started_at' => null,
            'generation_progressed_at' => null,
        ]);
    }

    /** Job en cola: la pantalla 05 está haciendo polling. */
    public function generating(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Generating,
            'generation_stage' => ProjectGenerationStage::Queued,
            'generation_attempt' => 1,
            'generation_started_at' => now(),
            'generation_progressed_at' => now(),
            'generation_error' => null,
        ]);
    }

    /** Malla generada y CPM resuelto. */
    public function ready(int $totalDurationDays = 39): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Ready,
            'generation_stage' => ProjectGenerationStage::Complete,
            'generation_attempt' => 1,
            'charged_generation_attempt' => 1,
            'generation_started_at' => now(),
            'generation_progressed_at' => now(),
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
