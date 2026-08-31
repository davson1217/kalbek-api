<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Goal;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\SpeakingAttempt;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'scenarios' => Scenario::query()->count(),
                'characters' => Character::query()->count(),
                'goals' => Goal::query()->count(),
                'npcLines' => NpcLine::query()->count(),
                'speakingAttempts' => SpeakingAttempt::query()->count(),
            ],
            'recentScenarios' => Scenario::query()
                ->with('character')
                ->latest('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Scenario $scenario): array => [
                    'id' => $scenario->id,
                    'slug' => $scenario->slug,
                    'title' => $scenario->title,
                    'status' => $scenario->status->value,
                    'cefr_level' => $scenario->cefr_level?->value,
                    'character' => $scenario->character?->name,
                    'updated_at' => $scenario->updated_at?->toDateTimeString(),
                ]),
        ]);
    }
}
