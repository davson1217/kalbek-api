<?php

namespace App\Models;

use App\CefrLevel;
use Database\Factories\NpcLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scene_id',
    'trigger_goal_id',
    'lt',
    'en',
    'cefr_level',
    'priority',
    'sort_order',
])]
class NpcLine extends Model
{
    /** @use HasFactory<NpcLineFactory> */
    use HasFactory;

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function triggerGoal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'trigger_goal_id');
    }

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'sort_order' => 'integer',
            'cefr_level' => CefrLevel::class,
        ];
    }
}
