<?php

namespace App\Models;

use Database\Factories\ScenePropFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scene_id',
    'type',
    'target_text',
    'support_translation',
    'price',
    'metadata',
    'sort_order',
])]
class SceneProp extends Model
{
    /** @use HasFactory<ScenePropFactory> */
    use HasFactory;

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
