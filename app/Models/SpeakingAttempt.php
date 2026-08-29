<?php

namespace App\Models;

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
    'feedback',
    'corrected_text',
    'metadata',
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
            'metadata' => 'array',
            'graded_at' => 'datetime',
        ];
    }
}
