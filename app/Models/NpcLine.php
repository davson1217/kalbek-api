<?php

namespace App\Models;

use Database\Factories\NpcLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scene_id',
    'lt',
    'en',
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

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
