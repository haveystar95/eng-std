<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminLogin;
use App\Modules\Admin\Application\Port\AdminReader;
use App\Modules\Admin\Application\Port\AdminSignOut;
use App\Modules\Admin\Presentation\Http\Request\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Admin sign-in / sign-out and the current admin. Translates HTTP only. */
final class AuthController
{
    public function __construct(
        private readonly AdminLogin $login,
        private readonly AdminReader $admins,
        private readonly AdminSignOut $signOut,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $device = $request->string('device_name')->toString();
        $session = $this->login->attempt(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $device !== '' ? $device : 'admin-panel',
        );

        if ($session === null) {
            return response()->json([
                'type' => 'https://api.wordtrainer.app/errors/invalid-credentials',
                'title' => 'Invalid credentials',
                'status' => Response::HTTP_UNAUTHORIZED,
                'code' => 'invalid_credentials',
                'detail' => 'Email or password is incorrect.',
            ], Response::HTTP_UNAUTHORIZED, ['Content-Type' => 'application/problem+json']);
        }

        return response()->json([
            'token' => $session->token,
            'admin' => ['id' => $session->id, 'email' => $session->email, 'name' => $session->name],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $this->admins->byId((string) $request->user()?->getAuthIdentifier());
        abort_if($admin === null, Response::HTTP_NOT_FOUND);

        return response()->json(['id' => $admin->id, 'email' => $admin->email, 'name' => $admin->name]);
    }

    public function logout(): Response
    {
        $this->signOut->revokeCurrent();

        return response()->noContent();
    }
}
