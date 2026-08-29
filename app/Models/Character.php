<?php

namespace App\Models;

use App\ContentStatus;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'role',
    'image_path',
    'intro',
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
