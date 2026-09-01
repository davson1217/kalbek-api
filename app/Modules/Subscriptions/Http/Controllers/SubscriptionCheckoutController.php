<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Subscriptions\Contracts\SubscriptionGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class SubscriptionCheckoutController extends Controller
{
    public function __invoke(Request $request, SubscriptionGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', Rule::in(['monthly', 'annual'])],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            return response()->json([
                'data' => $gateway->createCheckoutSession($user, $data['plan']),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }
    }
}
