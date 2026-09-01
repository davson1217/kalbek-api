<?php

namespace App\Modules\Subscriptions\Gateways;

use App\Models\User;
use App\Modules\Subscriptions\Contracts\SubscriptionGateway;
use App\Modules\Subscriptions\Models\Subscription;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeSubscriptionGateway implements SubscriptionGateway
{
    public function createCheckoutSession(User $user, string $plan): array
    {
        $priceId = match ($plan) {
            'annual' => config('subscriptions.stripe.annual_price_id'),
            default => config('subscriptions.stripe.monthly_price_id'),
        };

        if (! config('subscriptions.stripe.secret') || ! $priceId) {
            throw new RuntimeException('Stripe is not configured for subscription checkout yet.');
        }

        $response = Http::asForm()
            ->withToken((string) config('subscriptions.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'client_reference_id' => (string) $user->id,
                'customer_email' => $user->email,
                'success_url' => config('subscriptions.stripe.success_url').'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('subscriptions.stripe.cancel_url').'?checkout=cancelled',
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan' => $plan,
                ],
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'plan' => $plan,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Stripe checkout session could not be created.');
        }

        return [
            'checkout_url' => $response->json('url'),
            'provider' => 'stripe',
            'mode' => 'subscription',
            'session_id' => $response->json('id'),
        ];
    }

    public function createBillingPortalSession(User $user): array
    {
        if (! config('subscriptions.stripe.secret')) {
            throw new RuntimeException('Stripe is not configured for billing portal access yet.');
        }

        $customerId = Subscription::query()
            ->where('user_id', $user->id)
            ->where('provider', 'stripe')
            ->whereNotNull('provider_customer_id')
            ->latest('id')
            ->value('provider_customer_id');

        if (! $customerId) {
            throw new RuntimeException('No Stripe customer is connected to this account yet.');
        }

        $response = Http::asForm()
            ->withToken((string) config('subscriptions.stripe.secret'))
            ->post('https://api.stripe.com/v1/billing_portal/sessions', [
                'customer' => $customerId,
                'return_url' => config('subscriptions.stripe.success_url'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Stripe billing portal could not be opened.');
        }

        return [
            'portal_url' => $response->json('url'),
            'provider' => 'stripe',
        ];
    }
}
