<?php

namespace App\Services\Content;

use App\ContentStatus;
use App\Models\Goal;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\Scene;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuditScenarioContent
{
    /**
     * @return array<int, string>
     */
    public function handle(): array
    {
        $issues = [];

        Scenario::query()
            ->where('status', ContentStatus::Published)
            ->with(['scenes.goals.responseLines', 'scenes.npcLines.triggerGoal'])
            ->orderBy('sort_order')
            ->each(function (Scenario $scenario) use (&$issues): void {
                array_push($issues, ...$this->auditScenario($scenario));
            });

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    private function auditScenario(Scenario $scenario): array
    {
        $issues = [];
        $sceneSlugs = $scenario->scenes->pluck('slug')->all();

        if (! $scenario->start_scene_slug || ! in_array($scenario->start_scene_slug, $sceneSlugs, true)) {
            $issues[] = "Scenario [{$scenario->slug}] has an invalid start scene [{$scenario->start_scene_slug}].";
        }

        foreach ($scenario->scenes as $scene) {
            array_push($issues, ...$this->auditScene($scenario, $scene));
        }

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    private function auditScene(Scenario $scenario, Scene $scene): array
    {
        $issues = [];

        if (! $scene->npcLines->contains(fn (NpcLine $line): bool => $line->trigger_goal_id === null)) {
            $issues[] = "Scene [{$scenario->slug}/{$scene->slug}] has no opening/generic line.";
        }

        foreach ($scene->npcLines as $line) {
            if ($line->triggerGoal && $line->triggerGoal->scene_id !== $scene->id) {
                $issues[] = "Line [{$line->id}] in [{$scenario->slug}/{$scene->slug}] is attached to a goal from another scene.";
            }
        }

        foreach ($scene->goals as $goal) {
            array_push($issues, ...$this->auditGoal($scenario, $scene, $goal));
        }

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    private function auditGoal(Scenario $scenario, Scene $scene, Goal $goal): array
    {
        $issues = [];

        if ($goal->responseLines->isEmpty()) {
            $issues[] = "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] has no character replies.";
        }

        foreach ($this->exampleSpecificTokens($goal) as $token) {
            if ($goal->responseLines->contains(fn (NpcLine $line): bool => Str::contains($line->lt.' '.$line->en, $token, true))) {
                $issues[] = "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] has a reply that appears to hardcode example value [{$token}].";
            }
        }

        return $issues;
    }

    /**
     * @return Collection<int, string>
     */
    private function exampleSpecificTokens(Goal $goal): Collection
    {
        preg_match_all('/\b[\p{Lu}][\p{Ll}]{2,}\b/u', $goal->example, $matches, PREG_OFFSET_CAPTURE);

        return collect($matches[0] ?? [])
            ->reject(fn (array $match): bool => $match[1] === 0)
            ->map(fn (array $match): string => $match[0])
            ->reject(fn (string $token): bool => Str::contains($goal->label.' '.$goal->intent, $token, true))
            ->unique()
            ->values();
    }
}
