<?php

namespace App\Models;

use Database\Factories\ProjectClarificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectClarification extends Model
{
    /** @use HasFactory<ProjectClarificationFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'round',
        'generation_attempt',
        'key',
        'question',
        'rationale',
        'input_type',
        'options',
        'answer',
        'answered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'generation_attempt' => 'integer',
            'options' => 'array',
            'answered_at' => 'datetime',
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
     * @param  Builder<ProjectClarification>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('answered_at');
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }
}
