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
    public function publishedSummaries(): Collection
    {
        return Scenario::query()
            ->published()
            ->with('character')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function publishedDetail(Scenario $scenario): Scenario
    {
        abort_unless($scenario->status === ContentStatus::Published, 404);

        return $scenario->loadMissing([
            'character',
            'scenes.npcLines.triggerGoal',
            'scenes.goals.nextScene',
            'scenes.props',
        ]);
    }
}
