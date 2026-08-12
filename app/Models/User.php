<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AiModel;
use App\Enums\SubscriptionPlan;
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
            'plan' => SubscriptionPlan::class,
            'plan_expires_at' => 'datetime',
            'ai_credits_limit' => 'integer',
            'ai_credits_used' => 'integer',
            'ai_credits_reset_at' => 'datetime',
        ];
    }

    /**
     * Plan vigente. Una contratación vencida vale como plan gratuito aunque la
     * columna siga diciendo `pro`, para que un cobro que no se renovó no siga
     * dando acceso.
     */
    public function currentPlan(): SubscriptionPlan
    {
        if ($this->plan === SubscriptionPlan::Pro && $this->planHasExpired()) {
            return SubscriptionPlan::Free;
        }

        return $this->plan;
    }

    public function planHasExpired(): bool
    {
        return $this->plan_expires_at !== null && $this->plan_expires_at->isPast();
    }

    public function isOnProPlan(): bool
    {
        return $this->currentPlan() === SubscriptionPlan::Pro;
    }

    public function canUseAiModel(AiModel $model): bool
    {
        return $this->currentPlan()->allows($model);
    }

    /**
     * Activa el plan Pro por un período y sube la cuota de generaciones.
     *
     * Contratar sobre un plan vigente extiende desde el vencimiento actual, no
     * desde hoy, para no regalarle días al usuario ni quitárselos.
     */
    public function activateProPlan(): void
    {
        $from = $this->isOnProPlan() && $this->plan_expires_at !== null
            ? $this->plan_expires_at
            : now();

        $this->forceFill([
            'plan' => SubscriptionPlan::Pro,
            'plan_expires_at' => $from->copy()->addDays(SubscriptionPlan::PRO_PERIOD_DAYS),
            'ai_credits_limit' => SubscriptionPlan::Pro->monthlyCredits(),
        ])->save();
    }

    /**
     * Vuelve al plan gratuito. No devuelve las generaciones ya consumidas: el
     * límite baja y `remainingAiCredits()` se encarga de no dar negativo.
     */
    public function cancelProPlan(): void
    {
        $this->forceFill([
            'plan' => SubscriptionPlan::Free,
            'plan_expires_at' => null,
            'ai_credits_limit' => SubscriptionPlan::Free->monthlyCredits(),
        ])->save();
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
