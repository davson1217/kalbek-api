<?php

namespace Tests\Feature;

use App\Ai\Agents\SpeakingJudge;
use App\Models\SpeakingAttempt;
use App\Models\User;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\SubscriptionStatus;
use Database\Seeders\A1ScenarioSeeder;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\RestaurantScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Transcription;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_starts_a_three_day_trial_with_one_week_grace_window(): void
    {
        $this->seed(CharacterSeeder::class);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.profile.subscription.status', 'trialing')
            ->assertJsonPath('user.profile.subscription.is_trial_active', true)
            ->assertJsonPath('user.profile.subscription.free_scenario_limit', 2);

        $subscription = Subscription::query()->firstOrFail();

        $this->assertTrue($subscription->trial_ends_at->isBetween(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()));
        $this->assertTrue($subscription->grace_ends_at->isBetween(now()->addDays(10)->subMinute(), now()->addDays(10)->addMinute()));
    }

    public function test_trial_and_grace_users_only_see_cms_selected_free_scenarios_as_available(): void
    {
        $this->seed([CharacterSeeder::class, A1ScenarioSeeder::class, RestaurantScenarioSeeder::class]);
        $user = User::factory()->create();
        Subscription::factory()->for($user)->trialExpiredInGrace()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/scenarios?language=lt')
            ->assertOk()
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.1.available', true)
            ->assertJsonPath('data.2.available', false)
            ->assertJsonPath('data.2.is_free', false)
            ->assertJsonPath('data.2.availability_reason', 'subscription_required');
    }

    public function test_trial_and_grace_users_cannot_open_paid_scenario_details(): void
    {
        $this->seed([CharacterSeeder::class, A1ScenarioSeeder::class, RestaurantScenarioSeeder::class]);
        $user = User::factory()->create();
        Subscription::factory()->for($user)->trialExpiredInGrace()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/scenarios/parduotuveje')
            ->assertStatus(402)
            ->assertJsonPath('subscription.is_in_grace_period', true);
    }

    public function test_active_subscribers_can_access_all_scenarios(): void
    {
        $this->seed([CharacterSeeder::class, A1ScenarioSeeder::class, RestaurantScenarioSeeder::class]);
        $user = User::factory()->create();
        Subscription::factory()->for($user)->active()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/scenarios?language=lt')
            ->assertOk();

        foreach ($response->json('data') as $scenario) {
            $this->assertTrue($scenario['available']);
        }
    }

    public function test_speech_check_is_blocked_after_trial_expiry_even_during_grace(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $user = User::factory()->create();
        Subscription::factory()->for($user)->trialExpiredInGrace()->create();
        Sanctum::actingAs($user);
        Transcription::fake(['Norėčiau staliuko dviem.']);
        SpeakingJudge::fake([['pass' => true]]);

        $this->postJson('/api/v1/speak-check', $this->speechPayload())
            ->assertStatus(402)
            ->assertJsonPath('subscription.can_use_ai', false)
            ->assertJsonPath('subscription.is_in_grace_period', true);

        $this->assertSame(0, SpeakingAttempt::query()->count());
        Transcription::assertNothingGenerated();
    }

    public function test_active_subscriber_can_use_speech_check_after_trial_window(): void
    {
        $this->seed([CharacterSeeder::class, RestaurantScenarioSeeder::class]);
        $user = User::factory()->create();
        Subscription::factory()->for($user)->active()->create();
        Sanctum::actingAs($user);
        Transcription::fake(['Norėčiau staliuko dviem.']);
        SpeakingJudge::fake([[
            'pass' => true,
            'feedback' => 'That works well.',
            'corrected' => 'Norėčiau staliuko dviem.',
            'scores' => [
                'grammar' => 80,
                'vocabulary' => 80,
                'cohesion' => 80,
                'task_completion' => 90,
                'pronunciation' => null,
            ],
        ]]);

        $this->postJson('/api/v1/speak-check', $this->speechPayload())
            ->assertOk()
            ->assertJsonPath('pass', true);
    }

    public function test_subscription_checkout_requires_stripe_configuration(): void
    {
        Config::set('subscriptions.stripe.secret', null);
        Config::set('subscriptions.stripe.monthly_price_id', null);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/subscription/checkout', ['plan' => 'monthly'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Stripe is not configured for subscription checkout yet.');
    }

    public function test_subscription_checkout_creates_stripe_checkout_session(): void
    {
        Config::set('subscriptions.stripe.secret', 'sk_test_123');
        Config::set('subscriptions.stripe.monthly_price_id', 'price_monthly');
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ]),
        ]);
        $user = User::factory()->create(['email' => 'ada@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscription/checkout', ['plan' => 'monthly'])
            ->assertOk()
            ->assertJsonPath('data.provider', 'stripe')
            ->assertJsonPath('data.mode', 'subscription')
            ->assertJsonPath('data.session_id', 'cs_test_123')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_123');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk_test_123')
            && $request['mode'] === 'subscription'
            && $request['line_items'][0]['price'] === 'price_monthly'
            && $request['customer_email'] === 'ada@example.com'
            && $request['metadata']['user_id'] === (string) $user->id);
    }

    public function test_billing_portal_creates_stripe_portal_session(): void
    {
        Config::set('subscriptions.stripe.secret', 'sk_test_123');
        Http::fake([
            'api.stripe.com/v1/billing_portal/sessions' => Http::response([
                'url' => 'https://billing.stripe.com/p/session/test',
            ]),
        ]);
        $user = User::factory()->create();
        Subscription::factory()->for($user)->active()->create([
            'provider_customer_id' => 'cus_123',
            'provider_subscription_id' => 'sub_123',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscription/portal')
            ->assertOk()
            ->assertJsonPath('data.provider', 'stripe')
            ->assertJsonPath('data.portal_url', 'https://billing.stripe.com/p/session/test');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk_test_123')
            && $request['customer'] === 'cus_123');
    }

    public function test_subscription_plans_fetch_stripe_prices_and_calculate_annual_discount(): void
    {
        Cache::flush();
        Config::set('subscriptions.stripe.secret', 'sk_test_123');
        Config::set('subscriptions.stripe.monthly_price_id', 'price_monthly');
        Config::set('subscriptions.stripe.annual_price_id', 'price_annual');
        Http::fake([
            'api.stripe.com/v1/prices/price_monthly' => Http::response([
                'unit_amount' => 1000,
                'currency' => 'eur',
                'recurring' => ['interval' => 'month'],
            ]),
            'api.stripe.com/v1/prices/price_annual' => Http::response([
                'unit_amount' => 10000,
                'currency' => 'eur',
                'recurring' => ['interval' => 'year'],
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonPath('data.monthly.unit_amount', 1000)
            ->assertJsonPath('data.monthly.currency', 'eur')
            ->assertJsonPath('data.annual.unit_amount', 10000)
            ->assertJsonPath('data.annual.discount_percent', 17);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        Config::set('subscriptions.stripe.webhook_secret', 'whsec_test');

        $this->call(
            'POST',
            '/api/v1/stripe/webhook',
            server: ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=bad', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['type' => 'checkout.session.completed']),
        )->assertForbidden();
    }

    public function test_checkout_completed_webhook_creates_active_stripe_subscription(): void
    {
        Config::set('subscriptions.stripe.webhook_secret', 'whsec_test');
        $user = User::factory()->create();
        $payload = json_encode([
            'id' => 'evt_checkout',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'client_reference_id' => (string) $user->id,
                    'customer' => 'cus_123',
                    'subscription' => 'sub_123',
                    'metadata' => ['user_id' => (string) $user->id, 'plan' => 'annual'],
                ],
            ],
        ]);

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_customer_id' => 'cus_123',
            'provider_subscription_id' => 'sub_123',
            'plan' => 'annual',
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    public function test_subscription_updated_webhook_syncs_price_period_and_status(): void
    {
        Config::set('subscriptions.stripe.webhook_secret', 'whsec_test');
        Config::set('subscriptions.stripe.annual_price_id', 'price_annual');
        $user = User::factory()->create();
        $periodEnd = now()->addYear()->timestamp;
        $payload = json_encode([
            'id' => 'evt_subscription_updated',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'status' => 'active',
                    'current_period_end' => $periodEnd,
                    'metadata' => ['user_id' => (string) $user->id],
                    'items' => ['data' => [['price' => ['id' => 'price_annual']]]],
                    'cancel_at_period_end' => false,
                ],
            ],
        ]);

        $this->postStripeWebhook($payload)->assertOk();

        $subscription = Subscription::query()->where('provider_subscription_id', 'sub_123')->firstOrFail();

        $this->assertSame('annual', $subscription->plan);
        $this->assertSame('price_annual', $subscription->provider_price_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($periodEnd, $subscription->current_period_ends_at->timestamp);
    }

    public function test_subscription_deleted_webhook_cancels_local_subscription(): void
    {
        Config::set('subscriptions.stripe.webhook_secret', 'whsec_test');
        $user = User::factory()->create();
        Subscription::factory()->for($user)->active()->create([
            'provider_customer_id' => 'cus_123',
            'provider_subscription_id' => 'sub_123',
        ]);
        $canceledAt = now()->subMinute()->timestamp;
        $payload = json_encode([
            'id' => 'evt_subscription_deleted',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'status' => 'canceled',
                    'canceled_at' => $canceledAt,
                    'items' => ['data' => [['price' => ['id' => 'price_monthly']]]],
                ],
            ],
        ]);

        $this->postStripeWebhook($payload)->assertOk();

        $subscription = Subscription::query()->where('provider_subscription_id', 'sub_123')->firstOrFail();

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertSame($canceledAt, $subscription->cancelled_at->timestamp);
    }

    public function test_invoice_payment_failed_marks_subscription_past_due(): void
    {
        Config::set('subscriptions.stripe.webhook_secret', 'whsec_test');
        $user = User::factory()->create();
        Subscription::factory()->for($user)->active()->create([
            'provider_subscription_id' => 'sub_123',
        ]);
        $payload = json_encode([
            'id' => 'evt_invoice_failed',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_123',
                    'subscription' => 'sub_123',
                    'status' => 'open',
                ],
            ],
        ]);

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'provider_subscription_id' => 'sub_123',
            'status' => SubscriptionStatus::PastDue->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function speechPayload(): array
    {
        return [
            'scenario_id' => 'restoranas',
            'scene_id' => 'atvykimas',
            'goal_id' => 'ask-table-two',
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', str_repeat('a', 4096)),
            'intent' => 'Ask for a table for two people.',
            'example' => 'Norėčiau staliuko dviem, prašau.',
            'context' => 'At a Lithuanian restaurant.',
        ];
    }

    private function postStripeWebhook(string $payload)
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        return $this->call(
            'POST',
            '/api/v1/stripe/webhook',
            server: [
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $payload,
        );
    }
}
