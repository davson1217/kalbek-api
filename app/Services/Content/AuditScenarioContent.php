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
     * @return array<int, array{severity: string, category: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string, recommendation: string}>
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
     * @return array<int, array{severity: string, category: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string, recommendation: string}>
     */
    private function auditScenario(Scenario $scenario): array
    {
        $issues = [];
        $sceneSlugs = $scenario->scenes->pluck('slug')->all();

        if (! $scenario->start_scene_slug || ! in_array($scenario->start_scene_slug, $sceneSlugs, true)) {
            $issues[] = $this->issue(
                'critical',
                'structure',
                'scenario',
                $scenario,
                "Scenario [{$scenario->slug}] has an invalid start scene [{$scenario->start_scene_slug}].",
                'Set start_scene_slug to the slug of the first scene learners should enter.',
            );
        }

        if ($scenario->scenes->isEmpty()) {
            $issues[] = $this->issue(
                'critical',
                'structure',
                'scenario',
                $scenario,
                "Scenario [{$scenario->slug}] has no scenes.",
                'Create at least one scene before publishing the scenario.',
            );
        }

        if (! $scenario->cefr_level) {
            $issues[] = $this->issue(
                'warning',
                'level',
                'scenario',
                $scenario,
                "Scenario [{$scenario->slug}] has no CEFR level.",
                'Set a CEFR level so the judge and CMS editors understand the expected learner range.',
            );
        }

        array_push($issues, ...$this->auditReachability($scenario));

        foreach ($scenario->scenes as $scene) {
            array_push($issues, ...$this->auditScene($scenario, $scene));
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, category: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string, recommendation: string}>
     */
    private function auditScene(Scenario $scenario, Scene $scene): array
    {
        $issues = [];

        if ($this->levelExceeds($scene->cefr_level, $scenario->cefr_level)) {
            $issues[] = $this->issue(
                'warning',
                'level',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] is level [{$scene->cefr_level->value}] but its scenario is level [{$scenario->cefr_level->value}].",
                'Lower the scene level or raise the scenario level so the learner journey stays coherent.',
                scene: $scene,
            );
        }

        if (! $scene->cefr_level) {
            $issues[] = $this->issue(
                'warning',
                'level',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] has no CEFR level.",
                'Set a scene level, usually the same as the scenario unless this step intentionally changes difficulty.',
                scene: $scene,
            );
        }

        if (trim($scene->setting) === '') {
            $issues[] = $this->issue(
                'critical',
                'content',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] has an empty setting.",
                'Describe the role-play situation clearly enough for the judge and CMS editors.',
                scene: $scene,
            );
        }

        if (! $scene->npcLines->contains(fn (NpcLine $line): bool => $line->trigger_goal_id === null)) {
            $issues[] = $this->issue(
                'critical',
                'dialogue',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] has no opening/generic line.",
                'Add at least one character line with no trigger goal so the scene has something to say when it opens.',
                scene: $scene,
            );
        }

        if ($scene->goals->isEmpty()) {
            $issues[] = $this->issue(
                'warning',
                'structure',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] has no learner goals and will end the conversation if entered.",
                'Keep this only for an intentional terminal scene; otherwise add the next learner goal.',
                scene: $scene,
            );
        }

        foreach ($scene->npcLines as $line) {
            if ($line->triggerGoal && $line->triggerGoal->scene_id !== $scene->id) {
                $issues[] = $this->issue(
                    'critical',
                    'relationship',
                    'line',
                    $scenario,
                    "Line [{$line->id}] in [{$scenario->slug}/{$scene->slug}] is attached to a goal from another scene.",
                    'Attach replies only to goals in the same scene as the reply line.',
                    scene: $scene,
                    line: $line,
                );
            }

            if (trim($line->target_text) === '' || trim($line->support_translation) === '') {
                $issues[] = $this->issue(
                    'critical',
                    'content',
                    'line',
                    $scenario,
                    "Line [{$line->id}] in [{$scenario->slug}/{$scene->slug}] has empty target text or support translation.",
                    'Fill both the target-language text and the support translation.',
                    scene: $scene,
                    line: $line,
                );
            }

            if (! $line->cefr_level) {
                $issues[] = $this->issue(
                    'warning',
                    'level',
                    'line',
                    $scenario,
                    "Line [{$line->id}] in [{$scenario->slug}/{$scene->slug}] has no CEFR level.",
                    'Set a line level so reply variants can be selected safely for the learner range.',
                    scene: $scene,
                    line: $line,
                );
            }

            $lineParentLevel = $line->triggerGoal?->cefr_level ?? $scene->cefr_level ?? $scenario->cefr_level;
            if ($this->levelExceeds($line->cefr_level, $lineParentLevel)) {
                $issues[] = $this->issue(
                    'warning',
                    'level',
                    'line',
                    $scenario,
                    "Line [{$line->id}] in [{$scenario->slug}/{$scene->slug}] is level [{$line->cefr_level->value}] but its parent content is level [{$lineParentLevel->value}].",
                    'Lower the line level or move it under content that matches its difficulty.',
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
     * @return array<int, array{severity: string, category: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string, recommendation: string}>
     */
    private function auditGoal(Scenario $scenario, Scene $scene, Goal $goal): array
    {
        $issues = [];

        if ($goal->nextScene && $goal->nextScene->scenario_id !== $scenario->id) {
            $issues[] = $this->issue(
                'critical',
                'relationship',
                'goal',
                $scenario,
                "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] links to a scene outside this scenario.",
                'Choose a next scene that belongs to this scenario, or leave it empty if the goal should end the flow.',
                scene: $scene,
                goal: $goal,
            );
        }

        $goalParentLevel = $scene->cefr_level ?? $scenario->cefr_level;
        if ($this->levelExceeds($goal->cefr_level, $goalParentLevel)) {
            $issues[] = $this->issue(
                'warning',
                'level',
                'goal',
                $scenario,
                "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] is level [{$goal->cefr_level->value}] but its parent content is level [{$goalParentLevel->value}].",
                'Lower the goal level or move it to a scene with the matching difficulty.',
                scene: $scene,
                goal: $goal,
            );
        }

        if (! $goal->cefr_level) {
            $issues[] = $this->issue(
                'warning',
                'level',
                'goal',
                $scenario,
                "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] has no CEFR level.",
                'Set a goal level so the speaking judge evaluates against the intended difficulty.',
                scene: $scene,
                goal: $goal,
            );
        }

        if (trim($goal->label) === '' || trim($goal->intent) === '' || trim($goal->example) === '') {
            $issues[] = $this->issue(
                'critical',
                'content',
                'goal',
                $scenario,
                "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] has an empty label, intent, or example.",
                'Fill all goal fields: editor label, communicative intent, and model example.',
                scene: $scene,
                goal: $goal,
            );
        }

        if ($goal->responseLines->isEmpty()) {
            $issues[] = $this->issue(
                'critical',
                'dialogue',
                'goal',
                $scenario,
                "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] has no character replies.",
                'Add at least one NPC line attached to this goal so the character can respond after the learner succeeds.',
                scene: $scene,
                goal: $goal,
            );
        }

        foreach ($this->exampleSpecificTokens($goal) as $token) {
            if ($goal->responseLines->contains(fn (NpcLine $line): bool => Str::contains($line->target_text.' '.$line->support_translation, $token, true))) {
                $issues[] = $this->issue(
                    'warning',
                    'content',
                    'goal',
                    $scenario,
                    "Goal [{$scenario->slug}/{$scene->slug}/{$goal->slug}] has a reply that appears to hardcode example value [{$token}].",
                    'Rewrite the reply so it works for any learner answer, or make the learner-specific value part of the goal requirement.',
                    scene: $scene,
                    goal: $goal,
                );
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, category: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string, recommendation: string}>
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
                'structure',
                'scene',
                $scenario,
                "Scene [{$scenario->slug}/{$scene->slug}] is not reachable from the start scene [{$scenario->start_scene_slug}].",
                'Link to this scene from a previous goal or remove it from the published scenario.',
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
     * @return array{severity: string, category: string, scope: string, scenario_slug: string, scene_slug?: string, goal_slug?: string, line_id?: int, message: string, recommendation: string}
     */
    private function issue(
        string $severity,
        string $category,
        string $scope,
        Scenario $scenario,
        string $message,
        string $recommendation,
        ?Scene $scene = null,
        ?Goal $goal = null,
        ?NpcLine $line = null,
    ): array {
        return array_filter([
            'severity' => $severity,
            'category' => $category,
            'scope' => $scope,
            'scenario_slug' => $scenario->slug,
            'scene_slug' => $scene?->slug,
            'goal_slug' => $goal?->slug,
            'line_id' => $line?->id,
            'message' => $message,
            'recommendation' => $recommendation,
        ], fn ($value): bool => $value !== null);
    }
}
