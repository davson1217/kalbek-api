<?php

namespace App\Models;

use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'scenario_id',
    'completed',
    'best_score',
    'xp_earned',
    'attempts',
    'completed_at',
])]
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    protected $table = 'lesson_progress';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'best_score' => 'integer',
            'xp_earned' => 'integer',
            'attempts' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
