<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Subscriptions\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionStatusController extends Controller
{
    public function __invoke(Request $request, SubscriptionManager $subscriptions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $subscriptions->accessFor($user)->toArray(),
        ]);
    }
}
