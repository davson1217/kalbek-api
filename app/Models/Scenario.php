<?php

namespace App\Models;

use App\CefrLevel;
use App\ContentStatus;
use App\Models\Concerns\HasContentTranslations;
use Database\Factories\ScenarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'language_id',
    'character_id',
    'slug',
    'title',
    'subtitle',
    'description',
    'emoji',
    'tone',
    'cefr_level',
    'start_scene_slug',
    'status',
    'is_free',
    'sort_order',
    'published_at',
])]
class Scenario extends Model
{
    /** @use HasFactory<ScenarioFactory> */
    use HasContentTranslations;
    use HasFactory;

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('sort_order');
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function speakingAttempts(): HasMany
    {
        return $this->hasMany(SpeakingAttempt::class);
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
            'is_free' => 'boolean',
            'status' => ContentStatus::class,
            'cefr_level' => CefrLevel::class,
        ];
    }
}
