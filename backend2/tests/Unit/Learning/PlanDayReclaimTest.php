<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanDayId;
use App\Modules\Shared\Domain\ValueObject\Ulid;

/**
 * A DAY WHOSE WORKER DIED IS TAKEN BACK — вердикт владельца по GEN-1, замок на оба исхода.
 *
 * `generating` used to be a state only its own worker could leave; a worker that died mid-call left
 * the day «собирается» for ever. The rule: past the window, the first reclaim re-queues the day
 * (its spent attempt stays spent) and the second fails it with a reason.
 */
function claimedDay(int $attemptsBefore, DateTimeImmutable $claimedAt): PlanDay
{
    $day = PlanDay::reconstitute(
        id: PlanDayId::fromString(Ulid::generate()),
        planId: PlanId::fromString(Ulid::generate()),
        dayIndex: 1,
        kind: PlanDayKind::Intro,
        collectionId: null,
        title: 'День 1',
        outcomeText: null,
        skills: [],
        roleBrief: null,
        scheduledOn: null,
        status: PlanDayStatus::Pending,
        generationAttempts: $attemptsBefore,
        failReason: null,
    );
    expect($day->claim($claimedAt))->toBeTrue();

    return $day;
}

it('leaves a day inside its window alone', function () {
    $claimed = new DateTimeImmutable('2026-09-07 10:00:00');
    $day = claimedDay(0, $claimed);

    expect($day->reclaimStale($claimed->modify('+9 minutes'), 10))->toBeNull()
        ->and($day->status())->toBe(PlanDayStatus::Generating)
        ->and($day->claimedAt())->toEqual($claimed);
});

it('re-queues a stale day the first time, keeping the attempt it spent', function () {
    $claimed = new DateTimeImmutable('2026-09-07 10:00:00');
    $day = claimedDay(0, $claimed);

    expect($day->reclaimStale($claimed->modify('+11 minutes'), 10))->toBe(PlanDayStatus::Pending)
        ->and($day->status())->toBe(PlanDayStatus::Pending)
        ->and($day->generationAttempts())->toBe(1)
        ->and($day->failCode())->toBeNull()
        ->and($day->failReason())->toContain('не ответил')
        // …and it can be claimed again — that is the second attempt.
        ->and($day->claim($claimed->modify('+12 minutes')))->toBeTrue()
        ->and($day->generationAttempts())->toBe(2);
});

it('fails a stale day the second time, with a reason the payload carries', function () {
    $claimed = new DateTimeImmutable('2026-09-07 10:00:00');
    $day = claimedDay(1, $claimed);   // the second claim of this day

    expect($day->reclaimStale($claimed->modify('+10 minutes'), 10))->toBe(PlanDayStatus::Failed)
        ->and($day->status())->toBe(PlanDayStatus::Failed)
        ->and($day->failCode())->toBe(PlanDay::TIMED_OUT)
        ->and($day->failReason())->toContain('дважды')
        // Spent for good: no third claim.
        ->and($day->claim($claimed->modify('+20 minutes')))->toBeFalse();
});

it('does not touch a day that is not generating, or one claimed by a build that never stamped it', function () {
    $claimed = new DateTimeImmutable('2026-09-07 10:00:00');
    $ready = claimedDay(0, $claimed);
    $ready->markFailed('x');
    expect($ready->reclaimStale($claimed->modify('+1 day'), 10))->toBeNull();

    $unstamped = claimedDay(0, $claimed);
    $unstamped = PlanDay::reconstitute(
        id: $unstamped->id(), planId: $unstamped->planId(), dayIndex: 1, kind: PlanDayKind::Intro,
        collectionId: null, title: 'День 1', outcomeText: null, skills: [], roleBrief: null,
        scheduledOn: null, status: PlanDayStatus::Generating, generationAttempts: 1, failReason: null,
    );
    expect($unstamped->reclaimStale($claimed->modify('+1 day'), 10))->toBeNull();
});
