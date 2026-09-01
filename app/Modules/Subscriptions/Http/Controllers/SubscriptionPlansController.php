<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Stripe\StripePlanCatalog;
use Illuminate\Http\JsonResponse;

class SubscriptionPlansController extends Controller
{
    public function __invoke(StripePlanCatalog $plans): JsonResponse
    {
        return response()->json([
            'data' => $plans->plans(),
        ]);
    }
}
