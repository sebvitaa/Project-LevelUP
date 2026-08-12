<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectClarification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectClarification>
 */
class ProjectClarificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->awaitingInput(),
            'round' => 1,
            'generation_attempt' => 1,
            'key' => fake()->unique()->slug(2),
            'question' => fake()->sentence(),
            'rationale' => fake()->sentence(),
            'input_type' => 'text',
            'options' => null,
            'answer' => null,
            'answered_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'answer' => null,
            'answered_at' => null,
        ]);
    }

    public function answered(string $answer = 'Respuesta de prueba'): static
    {
        return $this->state(fn (): array => [
            'answer' => $answer,
            'answered_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>|null  $options
     */
    public function select(?array $options = null): static
    {
        return $this->state(fn (): array => [
            'input_type' => 'select',
            'options' => $options ?? ['Opción A', 'Opción B'],
        ]);
    }
}
