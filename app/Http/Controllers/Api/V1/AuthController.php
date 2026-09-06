<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Models\User;
use App\Services\Auth\AuthPayloadFactory;
use App\Services\Auth\RegisterUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterUser $registerUser,
        AuthPayloadFactory $payloadFactory,
    ): JsonResponse {
        $user = $registerUser->handle($request->validated());

        return response()->json($payloadFactory->make($user), 201);
    }

    public function login(LoginRequest $request, AuthPayloadFactory $payloadFactory): JsonResponse
    {
        logger()->info("Hello World!");
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json($payloadFactory->make($user));
    }

    public function me(Request $request, AuthPayloadFactory $payloadFactory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $payloadFactory->user($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
