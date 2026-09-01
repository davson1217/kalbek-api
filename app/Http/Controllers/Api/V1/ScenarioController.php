<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ScenarioResource;
use App\Http\Resources\Api\V1\ScenarioSummaryResource;
use App\Models\Scenario;
use App\Models\User;
use App\Modules\Subscriptions\SubscriptionManager;
use App\Services\Content\ScenarioPayloadBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScenarioController extends Controller
{
    public function index(Request $request, ScenarioPayloadBuilder $payloadBuilder, SubscriptionManager $subscriptions): AnonymousResourceCollection
    {
        $languageCode = $request->string('language')->trim()->lower()->value() ?: null;
        $scenarios = $payloadBuilder->publishedSummaries($languageCode);

        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            $access = $subscriptions->accessFor($user);
            $scenarios->each(function (Scenario $scenario) use ($access): void {
                $available = $access->canAccessScenario($scenario);
                $scenario->setAttribute('available_for_user', $available);
                $scenario->setAttribute('availability_reason', $available ? null : 'subscription_required');
            });
        }

        return ScenarioSummaryResource::collection($scenarios);
    }

    public function show(Scenario $scenario, Request $request, ScenarioPayloadBuilder $payloadBuilder, SubscriptionManager $subscriptions): ScenarioResource
    {
        $scenario->loadMissing('language');

        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            $access = $subscriptions->accessFor($user);

            if (! $access->canAccessScenario($scenario)) {
                abort(response()->json([
                    'message' => 'A subscription is required to open this scenario.',
                    'subscription' => $access->toArray(),
                ], 402));
            }
        }

        $detail = $payloadBuilder->publishedDetail($scenario);
        $detail->setAttribute('available_for_user', true);

        return ScenarioResource::make($detail);
    }
}
