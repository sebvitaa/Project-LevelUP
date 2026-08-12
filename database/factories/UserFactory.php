<?php

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Espeja los valores por defecto de la migración para que el modelo
            // en memoria tenga los mismos atributos que uno leído de la base.
            'plan' => SubscriptionPlan::Free,
            'plan_expires_at' => null,
            'ai_credits_limit' => SubscriptionPlan::Free->monthlyCredits(),
            'ai_credits_used' => 0,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Usuario que ya gastó toda su cuota mensual de generaciones. */
    public function withoutAiCredits(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_credits_used' => $attributes['ai_credits_limit'] ?? SubscriptionPlan::Free->monthlyCredits(),
        ]);
    }

    /** Usuario con el plan Pro vigente: accede al modelo avanzado. */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => SubscriptionPlan::Pro,
            'plan_expires_at' => now()->addDays(SubscriptionPlan::PRO_PERIOD_DAYS),
            'ai_credits_limit' => SubscriptionPlan::Pro->monthlyCredits(),
        ]);
    }

    /** Contrató el plan Pro pero ya se le venció: vuelve a las reglas del gratis. */
    public function expiredPro(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => SubscriptionPlan::Pro,
            'plan_expires_at' => now()->subDay(),
            'ai_credits_limit' => SubscriptionPlan::Pro->monthlyCredits(),
        ]);
    }
}
