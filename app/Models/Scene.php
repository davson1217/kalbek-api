<?php

namespace App\Models;

use Database\Factories\SceneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'scenario_id',
    'slug',
    'setting',
    'sort_order',
])]
class Scene extends Model
{
    /** @use HasFactory<SceneFactory> */
    use HasFactory;

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function npcLines(): HasMany
    {
        return $this->hasMany(NpcLine::class)->orderBy('sort_order');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class)->orderBy('sort_order');
    }

    public function props(): HasMany
    {
        return $this->hasMany(SceneProp::class)->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
