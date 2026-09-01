<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'kalbek',
            'plan' => 'trial',
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(3),
            'grace_ends_at' => now()->addDays(10),
        ];
    }

    public function active(string $plan = 'monthly'): static
    {
        return $this->state(fn () => [
            'provider' => 'stripe',
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'current_period_ends_at' => now()->addMonth(),
        ]);
    }

    public function trialExpiredInGrace(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(6),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired,
            'trial_ends_at' => now()->subDays(8),
            'grace_ends_at' => now()->subDay(),
        ]);
    }
}
