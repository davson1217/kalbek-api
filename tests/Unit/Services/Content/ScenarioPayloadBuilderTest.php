<?php

namespace Tests\Unit\Services\Content;

use App\Models\Scenario;
use App\Services\Content\ScenarioPayloadBuilder;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ScenarioPayloadBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_published_summaries_exclude_drafts(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        Scenario::factory()->draft()->create(['slug' => 'draft-scene']);

        $summaries = app(ScenarioPayloadBuilder::class)->publishedSummaries();

        $this->assertSame(['restoranas'], $summaries->pluck('slug')->all());
    }

    public function test_published_detail_loads_nested_relations(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $scenario = Scenario::query()->where('slug', 'restoranas')->firstOrFail();

        $detail = app(ScenarioPayloadBuilder::class)->publishedDetail($scenario);

        $this->assertTrue($detail->relationLoaded('character'));
        $this->assertTrue($detail->relationLoaded('scenes'));
        $this->assertTrue($detail->scenes->first()->relationLoaded('npcLines'));
        $this->assertTrue($detail->scenes->first()->relationLoaded('goals'));
        $this->assertSame('atvykimas', $detail->scenes->first()->slug);
    }
}
