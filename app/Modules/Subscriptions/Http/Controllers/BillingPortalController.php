<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Subscriptions\Contracts\SubscriptionGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BillingPortalController extends Controller
{
    public function __invoke(Request $request, SubscriptionGateway $gateway): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            return response()->json([
                'data' => $gateway->createBillingPortalSession($user),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }
    }
}
