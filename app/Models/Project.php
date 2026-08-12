<?php

namespace App\Models;

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
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
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
     * Porcentaje de avance: actividades completadas sobre el total.
     */
    public function completionPercentage(): int
    {
        $total = $this->activities()->count();

        if ($total === 0) {
            return 0;
        }

        return (int) round($this->activities()->whereNotNull('completed_at')->count() / $total * 100);
    }

    /**
     * Fecha de término proyectada según la ruta crítica.
     */
    public function projectedFinishDate(): ?Carbon
    {
        if ($this->total_duration_days === null) {
            return null;
        }

        return $this->starts_on->copy()->addDays($this->total_duration_days);
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

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeReady(Builder $query): void
    {
        $query->where('status', ProjectStatus::Ready);
    }
}
