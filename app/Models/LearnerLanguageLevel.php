<?php

namespace App\Models;

use App\CefrLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'language_code',
    'current_cefr_level',
    'confidence_score',
    'grammar_score',
    'vocabulary_score',
    'cohesion_score',
    'task_completion_score',
    'pronunciation_score',
    'evidence_attempts_count',
    'evidence_window_started_at',
    'evidence_window_ended_at',
    'last_evaluated_at',
])]
class LearnerLanguageLevel extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CefrLevelHistory::class);
    }

    protected function casts(): array
    {
        return [
            'current_cefr_level' => CefrLevel::class,
            'confidence_score' => 'integer',
            'grammar_score' => 'integer',
            'vocabulary_score' => 'integer',
            'cohesion_score' => 'integer',
            'task_completion_score' => 'integer',
            'pronunciation_score' => 'integer',
            'evidence_attempts_count' => 'integer',
            'evidence_window_started_at' => 'datetime',
            'evidence_window_ended_at' => 'datetime',
            'last_evaluated_at' => 'datetime',
        ];
    }
}
