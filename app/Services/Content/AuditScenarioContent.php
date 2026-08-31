<?php

namespace App\Services\Content;

use App\CefrLevel;
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
     * @return array<int, array{severity: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string}>
     */
    public function issues(?Scenario $onlyScenario = null, bool $publishedOnly = true): array
    {
        $issues = [];

        $query = Scenario::query()
            ->with(['scenes.goals.nextScene', 'scenes.goals.responseLines', 'scenes.npcLines.triggerGoal'])
            ->orderBy('sort_order');

        if ($onlyScenario) {
            $query->whereKey($onlyScenario->id);
        }

        if ($publishedOnly) {
            $query->where('status', ContentStatus::Published);
        }

        $query->each(function (Scenario $scenario) use (&$issues): void {
            array_push($issues, ...$this->auditScenario($scenario));
        });

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    public function handle(): array
    {
        return array_map(fn (array $issue): string => $issue['message'], $this->issues());
    }

    /**
     * @return array<int, array{severity: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string}>
     */
    private function auditScenario(Scenario $scenario): array
    {
        $issues = [];
        $sceneSlugs = $scenario->scenes->pluck('slug')->all();

        if (! $scenario->start_scene_slug || ! in_array($scenario->start_scene_slug, $sceneSlugs, true)) {
            $issues[] = $this->issue(
                'critical',
                'scenario',
                $scenario,
                "Scenario [{$scenario->slug}] has an invalid start scene [{$scenario->start_scene_slug}].",
            );
        }

        array_push($issues, ...$this->auditReachability($scenario));

        foreach ($scenario->scenes as $scene) {
            array_push($issues, ...$this->auditScene($scenario, $scene));
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string}>
     */
    private function auditScene(Scenario $scenario, Scene $scene): array
    {
        $issues = [];

        if ($this->levelExceeds($scene->cefr_level, $scenario->cefr_level)) {
            $issues[] = $this->issue(
                'warning',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] is level [{$scene->cefr_level->value}] but its scenario is level [{$scenario->cefr_level->value}].",
                scene: $scene,
            );
        }

        if (! $scene->npcLines->contains(fn (NpcLine $line): bool => $line->trigger_goal_id === null)) {
            $issues[] = $this->issue(
                'critical',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] has no opening/generic line.",
                scene: $scene,
            );
        }

        foreach ($scene->npcLines as $line) {
            if ($line->triggerGoal && $line->triggerGoal->scene_id !== $scene->id) {
                $issues[] = $this->issue(
                    'critical',
                    'line',
                    $scenario,
                    "Line [{$line->id}] in [{$scenario->slug}/{$scene->slug}] is attached to a goal from another scene.",
                    scene: $scene,
                    line: $line,
                );
            }

            $lineParentLevel = $line->triggerGoal?->cefr_level ?? $scene->cefr_level ?? $scenario->cefr_level;
            if ($this->levelExceeds($line->cefr_level, $lineParentLevel)) {
                $issues[] = $this->issue(
                    'warning',
                    'line',
                    $scenario,
                    "Line [{$line->id}] in [{$scenario->slug}/{$scene->slug}] is level [{$line->cefr_level->value}] but its parent content is level [{$lineParentLevel->value}].",
                    scene: $scene,
                    line: $line,
                );
            }
        }

        foreach ($scene->goals as $goal) {
            array_push($issues, ...$this->auditGoal($scenario, $scene, $goal));
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string}>
     */
    private function auditGoal(Scenario $scenario, Scene $scene, Goal $goal): array
    {
        $issues = [];

        if ($goal->nextScene && $goal->nextScene->scenario_id !== $scenario->id) {
            $issues[] = $this->issue(
                'critical',
                'goal',
                $scenario,
                "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] links to a scene outside this scenario.",
                scene: $scene,
                goal: $goal,
            );
        }

        $goalParentLevel = $scene->cefr_level ?? $scenario->cefr_level;
        if ($this->levelExceeds($goal->cefr_level, $goalParentLevel)) {
            $issues[] = $this->issue(
                'warning',
                'goal',
                $scenario,
                "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] is level [{$goal->cefr_level->value}] but its parent content is level [{$goalParentLevel->value}].",
                scene: $scene,
                goal: $goal,
            );
        }

        if ($goal->responseLines->isEmpty()) {
            $issues[] = $this->issue(
                'critical',
                'goal',
                $scenario,
                "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] has no character replies.",
                scene: $scene,
                goal: $goal,
            );
        }

        foreach ($this->exampleSpecificTokens($goal) as $token) {
            if ($goal->responseLines->contains(fn (NpcLine $line): bool => Str::contains($line->lt.' '.$line->en, $token, true))) {
                $issues[] = $this->issue(
                    'warning',
                    'goal',
                    $scenario,
                    "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] has a reply that appears to hardcode example value [{$token}].",
                    scene: $scene,
                    goal: $goal,
                );
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string}>
     */
    private function auditReachability(Scenario $scenario): array
    {
        if (! $scenario->start_scene_slug) {
            return [];
        }

        $scenesBySlug = $scenario->scenes->keyBy('slug');
        $start = $scenesBySlug->get($scenario->start_scene_slug);

        if (! $start) {
            return [];
        }

        $visited = [];
        $queue = [$start];

        while ($queue !== []) {
            /** @var Scene $scene */
            $scene = array_shift($queue);
            if (isset($visited[$scene->slug])) {
                continue;
            }

            $visited[$scene->slug] = true;

            foreach ($scene->goals as $goal) {
                if (! $goal->nextScene || $goal->nextScene->scenario_id !== $scenario->id) {
                    continue;
                }

                if (! isset($visited[$goal->nextScene->slug])) {
                    $queue[] = $goal->nextScene;
                }
            }
        }

        return $scenario->scenes
            ->reject(fn (Scene $scene): bool => isset($visited[$scene->slug]))
            ->map(fn (Scene $scene): array => $this->issue(
                'warning',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] is not reachable from the start scene [{$scenario->start_scene_slug}].",
                scene: $scene,
            ))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function exampleSpecificTokens(Goal $goal): Collection
    {
        preg_match_all('/\b[\p{Lu}][\p{Ll}]{2,}\b/u', $goal->example, $matches, PREG_OFFSET_CAPTURE);

        preg_match_all(
            '/(?:mano vardas|aš esu|esu iš|iš)\s+([\p{Lu}][\p{Ll}]{2,})\b/iu',
            $goal->example,
            $contextMatches,
        );

        $capitalizedTokens = collect($matches[0] ?? [])
            ->reject(fn (array $match): bool => $match[1] === 0)
            ->map(fn (array $match): string => $match[0]);

        return $capitalizedTokens
            ->merge($contextMatches[1] ?? [])
            ->reject(fn (string $token): bool => Str::contains($goal->label.' '.$goal->intent, $token, true))
            ->unique()
            ->values();
    }

    private function levelExceeds(?CefrLevel $child, ?CefrLevel $parent): bool
    {
        if (! $child || ! $parent) {
            return false;
        }

        return $child->rank() > $parent->rank();
    }

    /**
     * @return array{severity: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string}
     */
    private function issue(
        string $severity,
        string $scope,
        Scenario $scenario,
        string $message,
        ?Scene $scene = null,
        ?Goal $goal = null,
        ?NpcLine $line = null,
    ): array {
        return array_filter([
            'severity' => $severity,
            'scope' => $scope,
            'scenario_slug' => $scenario->slug,
            'scene_slug' => $scene?->slug,
            'goal_slug' => $goal?->slug,
            'line_id' => $line?->id,
            'message' => $message,
        ], fn ($value): bool => $value !== null);
    }
}
