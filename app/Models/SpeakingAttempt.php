<?php

namespace App\Models;

use App\CefrLevel;
use App\SpeakingAttemptStatus;
use Database\Factories\SpeakingAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'scenario_id',
    'scene_id',
    'goal_id',
    'status',
    'audio_path',
    'transcript',
    'passed',
    'score',
    'grammar_score',
    'vocabulary_score',
    'cohesion_score',
    'task_completion_score',
    'pronunciation_score',
    'overall_score',
    'attempt_cefr_level',
    'evaluation_provider',
    'evaluation_model',
    'feedback',
    'corrected_text',
    'metadata',
    'evaluated_at',
    'graded_at',
])]
class SpeakingAttempt extends Model
{
    /** @use HasFactory<SpeakingAttemptFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(AiEvaluation::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SpeakingAttemptStatus::class,
            'passed' => 'boolean',
            'score' => 'integer',
            'grammar_score' => 'integer',
            'vocabulary_score' => 'integer',
            'cohesion_score' => 'integer',
            'task_completion_score' => 'integer',
            'pronunciation_score' => 'integer',
            'overall_score' => 'integer',
            'attempt_cefr_level' => CefrLevel::class,
            'metadata' => 'array',
            'evaluated_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }
}
