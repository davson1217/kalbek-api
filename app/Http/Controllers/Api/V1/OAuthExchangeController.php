<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OAuthExchangeRequest;
use App\Models\User;
use App\Services\Auth\AuthPayloadFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class OAuthExchangeController extends Controller
{
    public function __invoke(OAuthExchangeRequest $request, AuthPayloadFactory $payloadFactory): JsonResponse
    {
        $code = $request->validated('code');
        $userId = Cache::pull("oauth:handoff:{$code}");

        if (! $userId) {
            throw ValidationException::withMessages([
                'code' => ['This login link has expired. Please try Google sign-in again.'],
            ]);
        }

        $user = User::query()->findOrFail($userId);

        return response()->json($payloadFactory->make($user));
    }
}
