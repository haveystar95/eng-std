<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controller;

use App\Modules\Identity\Application\Port\GoogleSignIn;
use App\Modules\Identity\Application\Port\SignOut;
use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Identity\Presentation\Http\Request\GoogleLoginRequest;
use App\Modules\Identity\Presentation\Http\Resource\UserResource;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Google sign-in, the authenticated user, and per-device logout. Translates only. */
final class AuthController
{
    public function __construct(
        private readonly GoogleSignIn $signIn,
        private readonly UserReader $users,
        private readonly SignOut $signOut,
    ) {}

    public function google(GoogleLoginRequest $request): JsonResponse
    {
        $deviceName = $request->string('device_name')->toString();

        $result = $this->signIn->authenticate(
            $request->string('id_token')->toString(),
            $deviceName !== '' ? $deviceName : 'mobile',
        );

        return response()->json([
            'token' => $result->token,
            'user' => UserResource::make($result->user)->resolve(),
        ]);
    }

    public function me(Request $request): UserResource
    {
        $view = $this->users->byId($this->actorId($request));
        abort_if($view === null, Response::HTTP_NOT_FOUND);

        return UserResource::make($view);
    }

    public function logout(): Response
    {
        $this->signOut->revokeCurrent();

        return response()->noContent();
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
