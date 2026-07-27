<?php

namespace App\Http\Controllers\Api;

use App\Actions\AuthenticateWithGoogle;
use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleLoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function google(GoogleLoginRequest $request, AuthenticateWithGoogle $action): JsonResponse
    {
        $result = $action->handle($request->validated()['id_token']);

        return response()->json([
            'token' => $result['token'],
            'user' => (new UserResource($result['user']))->resolve(),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('profile'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }
}
