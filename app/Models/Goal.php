<?php

namespace App\Models;

use App\CefrLevel;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scene_id',
    'next_scene_id',
    'slug',
    'label',
    'intent',
    'example',
    'cefr_level',
    'sort_order',
])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function nextScene(): BelongsTo
    {
        return $this->belongsTo(Scene::class, 'next_scene_id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'cefr_level' => CefrLevel::class,
        ];
    }
}
