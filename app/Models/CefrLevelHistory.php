<?php

namespace App\Models;

use App\CefrLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'learner_language_level_id',
    'language_code',
    'previous_cefr_level',
    'new_cefr_level',
    'confidence_score',
    'evidence_attempts_count',
    'reason',
    'evaluated_at',
])]
class CefrLevelHistory extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learnerLanguageLevel(): BelongsTo
    {
        return $this->belongsTo(LearnerLanguageLevel::class);
    }

    protected function casts(): array
    {
        return [
            'previous_cefr_level' => CefrLevel::class,
            'new_cefr_level' => CefrLevel::class,
            'confidence_score' => 'integer',
            'evidence_attempts_count' => 'integer',
            'evaluated_at' => 'datetime',
        ];
    }
}
