<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Qa;

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Learning\Application\Port\QaPlanClock;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\Cache;

/**
 * The QA plan clock, kept in the cache — NOT in a table (наряд DAY-FIX-2, «не UPDATE-ом по
 * таблице»). One integer per QA account, a month long; a cache flush resets every stand to the
 * real day, which is the honest default for a knob that exists only on a laptop.
 */
final readonly class CachedQaPlanClock implements QaPlanClock
{
    private const KEY = 'qa:plan_clock_shift:';

    private const TTL_SECONDS = 30 * 86400;

    public function __construct(private UserReader $users) {}

    public function isOpenFor(UserId $user): bool
    {
        // ОБА ЗАМКА уже сложены в одном месте — `qa_tools` пользователя (Identity). Второе правило
        // про то же самое однажды разошлось бы с первым, и разошлось бы в сторону открытой двери.
        return $this->users->byId($user)?->qaTools === true;
    }

    public function shiftFor(UserId $user): int
    {
        if (! $this->isOpenFor($user)) {
            return 0;
        }

        $raw = Cache::get(self::KEY . $user->value);

        return is_int($raw) ? $raw : 0;
    }

    public function set(UserId $user, int $days): void
    {
        if ($days === 0) {
            Cache::forget(self::KEY . $user->value);

            return;
        }

        Cache::put(self::KEY . $user->value, $days, self::TTL_SECONDS);
    }
}
