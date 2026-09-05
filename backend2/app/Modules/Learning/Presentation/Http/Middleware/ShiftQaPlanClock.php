<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Middleware;

use App\Modules\Learning\Application\Port\QaPlanClock;
use App\Modules\Learning\Application\Service\QaClockShift;
use App\Modules\Learning\Application\Service\ShiftedClock;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ПОДСТАВИТЬ «СЕГОДНЯ» на время ОДНОГО запроса QA-аккаунта (наряд DAY-FIX-2, дев-дверь смены дней).
 *
 * Two things, and both only for an account the door is open for and only while a shift is set:
 *
 *   1. the request's {@see QaClockShift} is set, and every {@see \App\Modules\Shared\Domain\Service\Clock}
 *      in the container is a {@see \App\Modules\Learning\Application\Service\ShiftableClock} reading
 *      it — so the plan's «today», the sitting's start and the day's census all see the shifted day,
 *      including the services resolved before this middleware ran;
 *   2. the dates the CLIENT stamps — a review's `answered_at`, an exposure's `shown_at` — are shifted
 *      by the same number of days on the way in. Without this half the phone would answer on the
 *      real day while the server lived a day later, and «closed today» would read «closed yesterday»:
 *      every stage would open without its night, which is the opposite of what the stand is for.
 *
 * Nothing is stored by this middleware; what it changes is one request's view of the calendar. The
 * shift is set on EVERY plan request — to zero for everybody else — so a number never outlives the
 * request that set it.
 */
final class ShiftQaPlanClock
{
    public function __construct(
        private readonly QaPlanClock $qaClock,
        private readonly QaClockShift $shift,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->getAuthIdentifier();
        $days = is_string($userId) && $userId !== ''
            ? $this->qaClock->shiftFor(UserId::fromString($userId))
            : 0;

        $this->shift->set($days);
        if ($days === 0) {
            return $next($request);
        }

        $this->shiftClientDates($request, $days);

        return $next($request);
    }

    /** `reviews[*].answered_at` and `exposures[*].shown_at`, moved by the same shift. */
    private function shiftClientDates(Request $request, int $days): void
    {
        foreach (['reviews' => 'answered_at', 'exposures' => 'shown_at'] as $list => $field) {
            $rows = $request->input($list);
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row) || ! is_string($row[$field] ?? null)) {
                    continue;
                }
                try {
                    $rows[$i][$field] = ShiftedClock::shift(new DateTimeImmutable($row[$field]), $days)->format(DATE_ATOM);
                } catch (\Exception) {
                    // An unparseable date is the FormRequest's business, not this middleware's.
                }
            }
            $request->merge([$list => $rows]);
        }
    }
}
