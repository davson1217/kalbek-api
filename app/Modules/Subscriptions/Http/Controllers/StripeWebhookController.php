<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Stripe\StripeSubscriptionSynchronizer;
use App\Modules\Subscriptions\Stripe\StripeWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeWebhookVerifier $verifier,
        StripeSubscriptionSynchronizer $synchronizer,
    ): JsonResponse {
        $verifier->verify($request);

        $event = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $synchronizer->handle($event);

        return response()->json(['received' => true]);
    }
}
