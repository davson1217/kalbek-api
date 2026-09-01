<?php

namespace App\Modules\Subscriptions\Contracts;

use App\Models\User;

interface SubscriptionGateway
{
    /**
     * @return array{checkout_url: string|null, provider: string, mode: string}
     */
    public function createCheckoutSession(User $user, string $plan): array;

    /**
     * @return array{portal_url: string, provider: string}
     */
    public function createBillingPortalSession(User $user): array;
}
