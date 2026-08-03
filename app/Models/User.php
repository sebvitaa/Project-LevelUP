<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ai_credits_limit' => 'integer',
            'ai_credits_used' => 'integer',
            'ai_credits_reset_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->latest();
    }

    public function remainingAiCredits(): int
    {
        return max(0, $this->ai_credits_limit - $this->ai_credits_used);
    }

    public function hasAiCreditsAvailable(): bool
    {
        return $this->remainingAiCredits() > 0;
    }

    /**
     * Descuenta una generación. Se llama al despachar el job, no al encolarlo,
     * para que un fallo de la API no le cueste una consulta al usuario.
     */
    public function consumeAiCredit(): void
    {
        $this->increment('ai_credits_used');
    }
}
