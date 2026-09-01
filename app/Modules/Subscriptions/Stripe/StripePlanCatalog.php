<?php

namespace App\Modules\Subscriptions\Stripe;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StripePlanCatalog
{
    /**
     * @return array{monthly: array<string, mixed>, annual: array<string, mixed>}
     */
    public function plans(): array
    {
        $monthly = $this->price('monthly', config('subscriptions.stripe.monthly_price_id'));
        $annual = $this->price('annual', config('subscriptions.stripe.annual_price_id'));

        return [
            'monthly' => $monthly,
            'annual' => [
                ...$annual,
                'discount_percent' => $this->discountPercent($monthly['unit_amount'], $annual['unit_amount']),
            ],
        ];
    }

    private function price(string $plan, ?string $priceId): array
    {
        if (! $priceId || ! config('subscriptions.stripe.secret')) {
            return $this->emptyPrice($plan, $priceId);
        }

        return Cache::remember("stripe-price:{$priceId}", now()->addHour(), function () use ($plan, $priceId): array {
            $response = Http::withToken((string) config('subscriptions.stripe.secret'))
                ->get("https://api.stripe.com/v1/prices/{$priceId}");

            if ($response->failed()) {
                return $this->emptyPrice($plan, $priceId);
            }

            return [
                'plan' => $plan,
                'price_id' => $priceId,
                'unit_amount' => $response->json('unit_amount') === null ? null : (int) $response->json('unit_amount'),
                'currency' => $response->json('currency'),
                'interval' => $response->json('recurring.interval'),
            ];
        });
    }

    private function emptyPrice(string $plan, ?string $priceId): array
    {
        return [
            'plan' => $plan,
            'price_id' => $priceId,
            'unit_amount' => null,
            'currency' => null,
            'interval' => $plan === 'annual' ? 'year' : 'month',
        ];
    }

    private function discountPercent(?int $monthlyAmount, ?int $annualAmount): ?int
    {
        if (! $monthlyAmount || ! $annualAmount) {
            return null;
        }

        $fullYearAmount = $monthlyAmount * 12;

        if ($fullYearAmount <= 0 || $annualAmount >= $fullYearAmount) {
            return 0;
        }

        return (int) round((1 - ($annualAmount / $fullYearAmount)) * 100);
    }
}
