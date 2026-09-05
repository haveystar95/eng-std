<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Port\QaPlanClock;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /qa/plan-clock` — «сдвинуть сегодня на N дней» для QA-аккаунта (наряд DAY-FIX-2).
 *
 * 404 and not 403 for everybody the door is shut for: whether the tool exists is not something an
 * ordinary account gets to learn, exactly as with a stranger's plan id.
 */
final class QaPlanClockController
{
    /** The furthest the stand may travel in either direction — a plan is at most a fortnight. */
    private const MAX_DAYS = 60;

    public function __construct(private readonly QaPlanClock $qaClock) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->openDoorFor($request);

        return new JsonResponse(['data' => ['days' => $this->qaClock->shiftFor($user)]]);
    }

    public function set(Request $request): JsonResponse
    {
        $user = $this->openDoorFor($request);
        $days = $request->validate([
            'days' => ['required', 'integer', 'min:' . -self::MAX_DAYS, 'max:' . self::MAX_DAYS],
        ])['days'];

        $this->qaClock->set($user, (int) $days);

        return new JsonResponse(['data' => ['days' => $this->qaClock->shiftFor($user)]]);
    }

    private function openDoorFor(Request $request): UserId
    {
        $user = UserId::fromString((string) $request->user()?->getAuthIdentifier());
        if (! $this->qaClock->isOpenFor($user)) {
            throw new NotFoundHttpException();
        }

        return $user;
    }
}
