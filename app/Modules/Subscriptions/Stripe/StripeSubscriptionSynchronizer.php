<?php

namespace App\Modules\Subscriptions\Stripe;

use App\Models\User;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class StripeSubscriptionSynchronizer
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        match ($event['type'] ?? null) {
            'checkout.session.completed' => $this->checkoutCompleted(Arr::get($event, 'data.object', [])),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->subscriptionChanged(Arr::get($event, 'data.object', [])),
            'invoice.payment_succeeded' => $this->invoicePaymentSucceeded(Arr::get($event, 'data.object', [])),
            'invoice.payment_failed' => $this->invoicePaymentFailed(Arr::get($event, 'data.object', [])),
            default => null,
        };
    }

    /** @param array<string, mixed> $session */
    private function checkoutCompleted(array $session): void
    {
        $user = $this->userFromPayload($session);

        if (! $user) {
            return;
        }

        $subscriptionId = Arr::get($session, 'subscription');
        $customerId = Arr::get($session, 'customer');

        if (! $subscriptionId) {
            return;
        }

        Subscription::query()->updateOrCreate(
            ['provider' => 'stripe', 'provider_subscription_id' => $subscriptionId],
            [
                'user_id' => $user->id,
                'provider_customer_id' => $customerId,
                'plan' => Arr::get($session, 'metadata.plan', 'monthly'),
                'status' => SubscriptionStatus::Active,
                'metadata' => ['checkout_session_id' => Arr::get($session, 'id')],
            ],
        );
    }

    /** @param array<string, mixed> $stripeSubscription */
    private function subscriptionChanged(array $stripeSubscription): void
    {
        $user = $this->userFromPayload($stripeSubscription)
            ?? $this->userFromKnownStripeIds($stripeSubscription);

        $subscriptionId = Arr::get($stripeSubscription, 'id');

        if (! $user || ! $subscriptionId) {
            return;
        }

        $priceId = Arr::get($stripeSubscription, 'items.data.0.price.id');
        $periodEnd = Arr::get($stripeSubscription, 'current_period_end');
        $canceledAt = Arr::get($stripeSubscription, 'canceled_at');
        $status = $this->mapStatus((string) Arr::get($stripeSubscription, 'status', 'active'));

        Subscription::query()->updateOrCreate(
            ['provider' => 'stripe', 'provider_subscription_id' => $subscriptionId],
            [
                'user_id' => $user->id,
                'provider_customer_id' => Arr::get($stripeSubscription, 'customer'),
                'provider_price_id' => $priceId,
                'plan' => $this->planFromPrice($priceId),
                'status' => $status,
                'current_period_ends_at' => $periodEnd ? CarbonImmutable::createFromTimestamp((int) $periodEnd) : null,
                'cancelled_at' => $canceledAt ? CarbonImmutable::createFromTimestamp((int) $canceledAt) : null,
                'metadata' => [
                    'stripe_status' => Arr::get($stripeSubscription, 'status'),
                    'cancel_at_period_end' => (bool) Arr::get($stripeSubscription, 'cancel_at_period_end', false),
                ],
            ],
        );
    }

    /** @param array<string, mixed> $invoice */
    private function invoicePaymentSucceeded(array $invoice): void
    {
        $this->updateInvoiceSubscription($invoice, SubscriptionStatus::Active);
    }

    /** @param array<string, mixed> $invoice */
    private function invoicePaymentFailed(array $invoice): void
    {
        $this->updateInvoiceSubscription($invoice, SubscriptionStatus::PastDue);
    }

    /** @param array<string, mixed> $invoice */
    private function updateInvoiceSubscription(array $invoice, SubscriptionStatus $status): void
    {
        $subscriptionId = Arr::get($invoice, 'subscription');

        if (! $subscriptionId) {
            return;
        }

        Subscription::query()
            ->where('provider', 'stripe')
            ->where('provider_subscription_id', $subscriptionId)
            ->update([
                'status' => $status,
                'metadata' => [
                    'latest_invoice_id' => Arr::get($invoice, 'id'),
                    'latest_invoice_status' => Arr::get($invoice, 'status'),
                ],
            ]);
    }

    /** @param array<string, mixed> $payload */
    private function userFromPayload(array $payload): ?User
    {
        $userId = Arr::get($payload, 'metadata.user_id') ?? Arr::get($payload, 'client_reference_id');

        return $userId ? User::query()->find((int) $userId) : null;
    }

    /** @param array<string, mixed> $payload */
    private function userFromKnownStripeIds(array $payload): ?User
    {
        $subscriptionId = Arr::get($payload, 'id') ?? Arr::get($payload, 'subscription');
        $customerId = Arr::get($payload, 'customer');

        $subscription = Subscription::query()
            ->where('provider', 'stripe')
            ->where(function ($query) use ($subscriptionId, $customerId): void {
                $query
                    ->when($subscriptionId, fn ($inner) => $inner->orWhere('provider_subscription_id', $subscriptionId))
                    ->when($customerId, fn ($inner) => $inner->orWhere('provider_customer_id', $customerId));
            })
            ->first();

        return $subscription?->user;
    }

    private function mapStatus(string $stripeStatus): SubscriptionStatus
    {
        return match ($stripeStatus) {
            'active', 'trialing' => SubscriptionStatus::Active,
            'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Canceled,
            default => SubscriptionStatus::Expired,
        };
    }

    private function planFromPrice(?string $priceId): string
    {
        return match ($priceId) {
            config('subscriptions.stripe.annual_price_id') => 'annual',
            config('subscriptions.stripe.monthly_price_id') => 'monthly',
            default => 'stripe',
        };
    }
}
