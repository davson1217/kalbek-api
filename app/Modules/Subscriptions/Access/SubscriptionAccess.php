<?php

namespace App\Modules\Subscriptions\Access;

use App\Models\Scenario;
use App\Modules\Subscriptions\SubscriptionStatus;
use Carbon\CarbonImmutable;

class SubscriptionAccess
{
    public function __construct(
        public readonly SubscriptionStatus $status,
        public readonly bool $hasActiveSubscription,
        public readonly bool $isTrialActive,
        public readonly bool $isInGracePeriod,
        public readonly bool $canUseAi,
        public readonly int $freeScenarioLimit,
        public readonly ?CarbonImmutable $trialEndsAt,
        public readonly ?CarbonImmutable $graceEndsAt,
        public readonly ?CarbonImmutable $currentPeriodEndsAt,
    ) {}

    public function canAccessScenarioPosition(int $position): bool
    {
        if ($this->hasActiveSubscription) {
            return true;
        }

        if (! $this->isTrialActive && ! $this->isInGracePeriod) {
            return false;
        }

        return $position <= $this->freeScenarioLimit;
    }

    public function canAccessScenario(Scenario $scenario): bool
    {
        if ($this->hasActiveSubscription) {
            return true;
        }

        if (! $this->isTrialActive && ! $this->isInGracePeriod) {
            return false;
        }

        return (bool) $scenario->is_free;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'has_active_subscription' => $this->hasActiveSubscription,
            'is_trial_active' => $this->isTrialActive,
            'is_in_grace_period' => $this->isInGracePeriod,
            'can_use_ai' => $this->canUseAi,
            'free_scenario_limit' => $this->freeScenarioLimit,
            'trial_ends_at' => $this->trialEndsAt?->toISOString(),
            'grace_ends_at' => $this->graceEndsAt?->toISOString(),
            'current_period_ends_at' => $this->currentPeriodEndsAt?->toISOString(),
        ];
    }
}
