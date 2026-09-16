<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Port\SlotJudgeQuota;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The slot judge's daily quota in the process's memory — the test suite's store (`plan.slot_judge.quota_store = array`).
 * The same key as Redis's — the learner and their LOCAL date — so a test that moves the clock past the learner's
 * midnight sees a fresh quota, and one instance per application, so a test's calls add up.
 */
final class ArraySlotJudgeQuota implements SlotJudgeQuota
{
    /** @var array<string, int> learner and local date → calls taken */
    private array $taken = [];

    public function take(UserId $user, DateTimeImmutable $now, DateTimeZone $zone, int $cap): bool
    {
        $key = $user->value.':'.$now->setTimezone($zone)->format('Y-m-d');
        $count = $this->taken[$key] ?? 0;
        if ($count >= $cap) {
            return false;
        }
        $this->taken[$key] = $count + 1;

        return true;
    }
}
