<?php

namespace App\Models;

use App\CefrLevel;
use App\ContentStatus;
use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'language_id',
    'slug',
    'title',
    'description',
    'cefr_level',
    'status',
    'sort_order',
    'published_at',
])]
class Unit extends Model
{
    use HasContentTranslations;
    use HasFactory;

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(Scenario::class)->orderBy('sort_order')->orderBy('title');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'status' => ContentStatus::class,
            'cefr_level' => CefrLevel::class,
        ];
    }
}
