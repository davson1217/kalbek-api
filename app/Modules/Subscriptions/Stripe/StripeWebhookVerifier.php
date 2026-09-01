<?php

namespace App\Modules\Subscriptions\Stripe;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StripeWebhookVerifier
{
    public function verify(Request $request): void
    {
        $secret = (string) config('subscriptions.stripe.webhook_secret');

        if ($secret === '') {
            throw new AccessDeniedHttpException('Stripe webhook secret is not configured.');
        }

        $signature = (string) $request->header('Stripe-Signature', '');
        $parts = collect(explode(',', $signature))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return $key && $value ? [$key => $value] : [];
            });

        $timestamp = $parts->get('t');
        $expected = $parts->get('v1');

        if (! $timestamp || ! $expected) {
            throw new AccessDeniedHttpException('Stripe webhook signature is malformed.');
        }

        $signedPayload = $timestamp.'.'.$request->getContent();
        $computed = hash_hmac('sha256', $signedPayload, $secret);

        if (! hash_equals($computed, $expected)) {
            throw new AccessDeniedHttpException('Stripe webhook signature is invalid.');
        }

        $tolerance = (int) config('subscriptions.stripe.webhook_tolerance_seconds', 300);

        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            throw new AccessDeniedHttpException('Stripe webhook timestamp is outside the allowed tolerance.');
        }
    }
}
