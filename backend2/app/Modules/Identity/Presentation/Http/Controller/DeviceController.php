<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controller;

use App\Modules\Identity\Application\Command\RecordVisit;
use App\Modules\Identity\Application\Command\RecordVisitHandler;
use App\Modules\Identity\Application\Command\RegisterPushToken;
use App\Modules\Identity\Application\Command\RegisterPushTokenHandler;
use App\Modules\Identity\Application\Command\RemovePushToken;
use App\Modules\Identity\Application\Command\RemovePushTokenHandler;
use App\Modules\Identity\Presentation\Http\Request\PushTokenRequest;
use App\Modules\Identity\Presentation\Http\Request\RemovePushTokenRequest;
use App\Modules\Identity\Presentation\Http\Request\VisitRequest;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The device's side of notifications: its push address and its visits. Registration answers `push_enabled`; the rest is 204. */
final class DeviceController
{
    public function putPushToken(PushTokenRequest $request, RegisterPushTokenHandler $handler): JsonResponse
    {
        $data = $request->validated();
        $enabled = $handler(new RegisterPushToken(
            userId: self::actor($request),
            platform: (string) $data['platform'],
            token: (string) $data['token'],
            locale: isset($data['locale']) ? (string) $data['locale'] : null,
            timezone: isset($data['timezone']) ? (string) $data['timezone'] : null,
        ));

        return response()->json(['data' => ['push_enabled' => $enabled]]);
    }

    public function deletePushToken(RemovePushTokenRequest $request, RemovePushTokenHandler $handler): Response
    {
        $data = $request->validated();
        $handler(new RemovePushToken((string) $data['platform'], (string) $data['token'], self::actor($request)));

        return response()->noContent();
    }

    public function visit(VisitRequest $request, RecordVisitHandler $handler): Response
    {
        $handler(new RecordVisit(self::actor($request)));

        return response()->noContent();
    }

    private static function actor(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
