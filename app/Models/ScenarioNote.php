<?php

namespace App\Models;

use App\CefrLevel;
use App\ContentStatus;
use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scenario_id',
    'title',
    'body',
    'cefr_level',
    'estimated_minutes',
    'status',
])]
class ScenarioNote extends Model
{
    use HasContentTranslations;
    use HasFactory;

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    protected function casts(): array
    {
        return [
            'cefr_level' => CefrLevel::class,
            'estimated_minutes' => 'integer',
            'status' => ContentStatus::class,
        ];
    }
}
