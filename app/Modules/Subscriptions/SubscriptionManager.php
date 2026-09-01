<?php

namespace App\Modules\Subscriptions;

use App\Models\User;
use App\Modules\Subscriptions\Access\SubscriptionAccess;
use App\Modules\Subscriptions\Models\Subscription;
use Carbon\CarbonImmutable;

class SubscriptionManager
{
    public function startTrial(User $user): Subscription
    {
        $now = CarbonImmutable::now();
        $trialEndsAt = $now->addDays((int) config('subscriptions.trial_days'));
        $graceEndsAt = $trialEndsAt->addDays((int) config('subscriptions.grace_days'));

        return Subscription::query()->firstOrCreate(
            ['user_id' => $user->id, 'provider' => 'kalbek'],
            [
                'plan' => 'trial',
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $trialEndsAt,
                'grace_ends_at' => $graceEndsAt,
            ],
        );
    }

    public function accessFor(User $user): SubscriptionAccess
    {
        $subscription = $user->subscriptions()
            ->orderByRaw("status = 'active' desc")
            ->latest('id')
            ->first();

        if (! $subscription) {
            $subscription = $this->startTrial($user);
        }

        $now = CarbonImmutable::now();
        $status = $subscription->status;
        $trialEndsAt = $subscription->trial_ends_at?->toImmutable();
        $graceEndsAt = $subscription->grace_ends_at?->toImmutable();
        $periodEndsAt = $subscription->current_period_ends_at?->toImmutable();
        $hasActiveSubscription = $status === SubscriptionStatus::Active
            && (! $periodEndsAt || $periodEndsAt->isFuture());
        $isTrialActive = $status === SubscriptionStatus::Trialing
            && $trialEndsAt !== null
            && $trialEndsAt->isFuture();
        $isInGracePeriod = ! $hasActiveSubscription
            && ! $isTrialActive
            && $graceEndsAt !== null
            && $graceEndsAt->isFuture();

        if (! $hasActiveSubscription && ! $isTrialActive && ! $isInGracePeriod && $status !== SubscriptionStatus::Expired) {
            $subscription->forceFill(['status' => SubscriptionStatus::Expired])->save();
            $status = SubscriptionStatus::Expired;
        }

        return new SubscriptionAccess(
            status: $status,
            hasActiveSubscription: $hasActiveSubscription,
            isTrialActive: $isTrialActive,
            isInGracePeriod: $isInGracePeriod,
            canUseAi: $hasActiveSubscription || $isTrialActive,
            freeScenarioLimit: (int) config('subscriptions.free_scenario_limit'),
            trialEndsAt: $trialEndsAt,
            graceEndsAt: $graceEndsAt,
            currentPeriodEndsAt: $periodEndsAt,
        );
    }
}
