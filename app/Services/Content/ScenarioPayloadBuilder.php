<?php

namespace App\Services\Content;

use App\ContentStatus;
use App\Models\Scenario;
use Illuminate\Database\Eloquent\Collection;

class ScenarioPayloadBuilder
{
    /**
     * @return Collection<int, Scenario>
     */
    public function publishedSummaries(?string $languageCode = null): Collection
    {
        return Scenario::query()
            ->published()
            ->when($languageCode, fn ($query) => $query->whereHas('language', fn ($language) => $language->where('code', $languageCode)))
            ->with(['character', 'language', 'translations'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function publishedDetail(Scenario $scenario): Scenario
    {
        abort_unless($scenario->status === ContentStatus::Published, 404);

        return $scenario->loadMissing([
            'character',
            'language',
            'translations',
            'scenes.translations',
            'scenes.npcLines.translations',
            'scenes.npcLines.triggerGoal',
            'scenes.goals.translations',
            'scenes.goals.nextScene',
            'scenes.props.translations',
        ]);
    }
}
