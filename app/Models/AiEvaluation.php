<?php

namespace App\Models;

use Database\Factories\AiEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'speaking_attempt_id',
    'ai_prompt_id',
    'provider',
    'model',
    'input',
    'output',
    'prompt_tokens',
    'completion_tokens',
    'total_tokens',
    'latency_ms',
    'status',
    'error_message',
])]
class AiEvaluation extends Model
{
    /** @use HasFactory<AiEvaluationFactory> */
    use HasFactory;

    public function speakingAttempt(): BelongsTo
    {
        return $this->belongsTo(SpeakingAttempt::class);
    }

    public function aiPrompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class);
    }

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }
}
