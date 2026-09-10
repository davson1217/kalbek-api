<?php

namespace App\Models;

use App\ContentStatus;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'language_id',
    'slug',
    'name',
    'role',
    'image_path',
    'intro',
    'tts_voice',
    'speaking_style',
    'praise_lines',
    'encouragement_lines',
    'sort_order',
    'status',
])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    public function scenarios(): HasMany
    {
        return $this->hasMany(Scenario::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'praise_lines' => 'array',
            'encouragement_lines' => 'array',
            'sort_order' => 'integer',
            'status' => ContentStatus::class,
        ];
    }
}
