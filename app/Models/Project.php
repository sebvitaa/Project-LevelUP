<?php

namespace App\Models;

use App\Enums\AiModel;
use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const RISK_THRESHOLD_DAYS = 7;

    protected $attributes = [
        'generation_attempt' => 0,
    ];

    protected $fillable = [
        'name',
        'type',
        'prompt',
        'starts_on',
        'deadline',
        'team_size',
        'ai_model',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'ai_model' => AiModel::class,
            'status' => ProjectStatus::class,
            'generation_stage' => ProjectGenerationStage::class,
            'starts_on' => 'date',
            'deadline' => 'date',
            'generated_at' => 'datetime',
            'generation_started_at' => 'datetime',
            'generation_progressed_at' => 'datetime',
            'team_size' => 'integer',
            'total_duration_days' => 'integer',
            'generation_attempt' => 'integer',
            'charged_generation_attempt' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('grid_column')->orderBy('grid_row');
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function criticalActivities(): HasMany
    {
        return $this->activities()->where('is_critical', true);
    }

    /**
     * @return HasMany<ProjectClarification, $this>
     */
    public function clarifications(): HasMany
    {
        return $this->hasMany(ProjectClarification::class)->orderBy('round')->orderBy('id');
    }

    /**
     * Preguntas sin respuesta del intento vigente.
     *
     * @return HasMany<ProjectClarification, $this>
     */
    public function pendingClarifications(): HasMany
    {
        return $this->clarifications()
            ->where('generation_attempt', $this->generation_attempt)
            ->pending();
    }

    /**
     * Porcentaje de avance: actividades completadas sobre el total.
     */
    public function completionPercentage(): int
    {
        [$total, $completed] = $this->activityCounts();

        if ($total === 0) {
            return 0;
        }

        return (int) round($completed / $total * 100);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function activityCounts(): array
    {
        $hasCounts = array_key_exists('activities_count', $this->attributes)
            && array_key_exists('completed_activities_count', $this->attributes);

        if ($hasCounts) {
            return [
                (int) $this->attributes['activities_count'],
                (int) $this->attributes['completed_activities_count'],
            ];
        }

        return [
            $this->activities()->count(),
            $this->activities()->whereNotNull('completed_at')->count(),
        ];
    }

    /**
     * Fecha de término proyectada según la ruta crítica.
     */
    public function projectedFinishDate(): ?Carbon
    {
        if ($this->total_duration_days === null) {
            return null;
        }

        return $this->starts_on->copy()->addDays($this->total_duration_days - 1);
    }

    /**
     * Días de atraso respecto a la fecha límite. Negativo significa holgura.
     */
    public function daysBehindSchedule(): ?int
    {
        $finish = $this->projectedFinishDate();

        if ($finish === null || $this->deadline === null) {
            return null;
        }

        return (int) $this->deadline->diffInDays($finish, false);
    }

    public function isOverdue(): bool
    {
        return ($this->daysBehindSchedule() ?? 0) > 0;
    }

    public function isCompleted(): bool
    {
        return $this->completionPercentage() === 100;
    }

    public function isAtRisk(): bool
    {
        if ($this->status === ProjectStatus::Failed) {
            return true;
        }

        if ($this->isCompleted()) {
            return false;
        }

        $daysBehind = $this->daysBehindSchedule();

        return $daysBehind !== null && $daysBehind > -self::RISK_THRESHOLD_DAYS;
    }

    /**
     * @return 'done'|'critical'|'slack'|'brand'
     */
    public function healthTone(): string
    {
        return match (true) {
            $this->isCompleted() => 'done',
            $this->isOverdue(), $this->status === ProjectStatus::Failed => 'critical',
            $this->isAtRisk() => 'slack',
            $this->status === ProjectStatus::Ready => 'done',
            default => 'brand',
        };
    }

    public function isCurrentGenerationAttempt(int $attempt): bool
    {
        return $this->generation_attempt === $attempt
            && $this->status === ProjectStatus::Generating;
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeReady(Builder $query): void
    {
        $query->where('status', ProjectStatus::Ready);
    }
}
