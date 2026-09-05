<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * ПОДСТАНОВКА «СЕГОДНЯ» ДЛЯ QA-АККАУНТА — наряд DAY-FIX-2 (разрешение «смена дней»).
 *
 * The plan's ladder is measured in days, and a live run of «день 1 → день 2 → финал» cannot be
 * walked in one afternoon without moving the clock. Moving the ROWS (`qa:time-travel`) rewrites an
 * append-only log; this moves the CLOCK, for one account, by a number of days kept beside the
 * account rather than written into any table. «Не UPDATE-ом по таблице.»
 *
 * The door is the SAME door as the password-less sign-in and the transcript substitution: the
 * account is marked `is_qa` AND the environment is not production with the flag on. The port asks
 * the one place that folds both locks ({@see \App\Modules\Identity\Application\Port\UserReader},
 * `qa_tools`), so a second rule about the same thing cannot drift open.
 */
interface QaPlanClock
{
    /** Is the door open for this account at all — QA-marked, behind the open gate. */
    public function isOpenFor(UserId $user): bool;

    /** Days added to «now» for this account; 0 for everybody the door is shut for. */
    public function shiftFor(UserId $user): int;

    /** Set the shift. The caller has already asked {@see isOpenFor()}. */
    public function set(UserId $user, int $days): void;
}
