<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'duration_days',
        'grid_column',
        'grid_row',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'early_start' => 'integer',
            'early_finish' => 'integer',
            'late_start' => 'integer',
            'late_finish' => 'integer',
            'slack' => 'integer',
            'is_critical' => 'boolean',
            'grid_column' => 'integer',
            'grid_row' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Actividades que deben terminar antes de que esta pueda empezar.
     *
     * @return BelongsToMany<Activity, $this>
     */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(
            Activity::class,
            'activity_dependencies',
            'activity_id',
            'predecessor_id'
        )->withTimestamps();
    }

    /**
     * Actividades que no pueden empezar hasta que esta termine.
     *
     * @return BelongsToMany<Activity, $this>
     */
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(
            Activity::class,
            'activity_dependencies',
            'predecessor_id',
            'activity_id'
        )->withTimestamps();
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Fecha de inicio en calendario, derivada del inicio temprano.
     */
    public function startDate(): ?Carbon
    {
        if ($this->early_start === null) {
            return null;
        }

        return $this->project->starts_on->copy()->addDays($this->early_start);
    }

    /**
     * Fecha de término en calendario, derivada del fin temprano.
     */
    public function finishDate(): ?Carbon
    {
        if ($this->early_finish === null) {
            return null;
        }

        return $this->project->starts_on->copy()->addDays($this->early_finish);
    }
}
