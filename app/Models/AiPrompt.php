<?php

namespace App\Models;

use App\AiPromptPurpose;
use Database\Factories\AiPromptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purpose',
    'version',
    'provider',
    'model',
    'system_message',
    'parameters',
    'active',
])]
class AiPrompt extends Model
{
    /** @use HasFactory<AiPromptFactory> */
    use HasFactory;

    public function evaluations(): HasMany
    {
        return $this->hasMany(AiEvaluation::class);
    }

    protected function casts(): array
    {
        return [
            'purpose' => AiPromptPurpose::class,
            'version' => 'integer',
            'parameters' => 'array',
            'active' => 'boolean',
        ];
    }
}
