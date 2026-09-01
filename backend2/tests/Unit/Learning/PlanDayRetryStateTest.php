<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Exception\InvalidPlanTransition;
use App\Modules\Learning\Domain\ValueObject\PlanDayId;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;

/**
 * The two-attempt cap, and the one door out of it that is not a raw UPDATE.
 */
function planDay(PlanDayStatus $status, int $attempts, array $violations = []): PlanDay
{
    return PlanDay::reconstitute(
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
        status: $status,
        generationAttempts: $attempts,
        failReason: 'что-то не то',
        lastViolations: $violations,
    );
}

it('keeps the LAST attempt`s violations and drops the older ones', function () {
    $day = planDay(PlanDayStatus::Generating, 1);

    $day->markFailed('первая', ['day.a: раз', 'day.b: два']);
    $day->markFailed('вторая', ['day.b: два', 'day.c: три', 'day.c: три']);

    // Accumulation was the previous наряд's own conclusion, and the live run refuted it: the third
    // call, handed a growing list of quoted defects, returned the quoted cards
    // (`docs/research/plan-v0.3-run.md`, второй заход). Deduplicated within the attempt, replaced
    // between attempts.
    expect($day->lastViolations())->toBe(['day.b: два', 'day.c: три']);
});

it('counts the repair APART from the attempt — a repaired first run still has its second (Д-18)', function () {
    // The live run's day 2 made two P2 calls under a cap of two and its row said «3 attempts»,
    // because the repair was charged as one. An attempt is a DAY call; the repair is money in a
    // column of its own.
    $day = planDay(PlanDayStatus::Generating, 1);

    $day->markFailed('день и починка', ['day.a: раз'], repairCalls: 1);

    expect($day->generationAttempts())->toBe(1)
        ->and($day->repairCalls())->toBe(1)
        ->and($day->paidCalls())->toBe(2)
        // The budget is P2 calls, and one is left.
        ->and($day->status())->toBe(PlanDayStatus::Pending)
        ->and($day->claim())->toBeTrue();
});

it('counts the repair IDENTICALLY on the written day and on the refused one (Д-18)', function () {
    // Day 1 of the live run spent P2 + P2R and came out `ready` saying it had cost one call; day 2
    // spent the same two and came out saying three. Same spending, two numbers — which is what made
    // the counter unusable as a budget.
    $written = planDay(PlanDayStatus::Generating, 1);
    $written->markReady(CollectionId::fromString(Ulid::generate()), repairCalls: 1);

    $refused = planDay(PlanDayStatus::Generating, 1);
    $refused->markFailed('не прошёл', ['day.a: раз'], repairCalls: 1);

    expect($written->repairCalls())->toBe($refused->repairCalls())
        ->and($written->paidCalls())->toBe($refused->paidCalls())
        ->and($written->paidCalls())->toBe(2);
});

it('spends the day only when the DAY calls run out, and the repairs are still counted', function () {
    $day = planDay(PlanDayStatus::Generating, 1);
    $day->markFailed('первый заход', ['day.a: раз'], repairCalls: 1);

    expect($day->claim())->toBeTrue()
        ->and($day->generationAttempts())->toBe(PlanDay::MAX_ATTEMPTS);

    $day->markFailed('второй заход', ['day.b: два'], repairCalls: 1);

    expect($day->status())->toBe(PlanDayStatus::Failed)
        ->and($day->generationAttempts())->toBe(2)
        // Two runs, one repair each — the structural ceiling, and it is what the day actually cost.
        ->and($day->repairCalls())->toBe(PlanDay::MAX_REPAIR_CALLS)
        ->and($day->paidCalls())->toBe(4);
});

it('never charges more repairs than a day can structurally make', function () {
    $day = planDay(PlanDayStatus::Generating, 1);

    // A replayed `FinishPlanDay` must not inflate a money column.
    $day->markFailed('раз', [], repairCalls: 1);
    $day->markFailed('раз', [], repairCalls: 1);
    $day->markFailed('раз', [], repairCalls: 1);

    expect($day->repairCalls())->toBe(PlanDay::MAX_REPAIR_CALLS);
});

it('leaves an ordinary failed run one attempt, exactly as before', function () {
    $day = planDay(PlanDayStatus::Generating, 1);

    $day->markFailed('только день', ['day.a: раз']);

    expect($day->generationAttempts())->toBe(1)
        ->and($day->status())->toBe(PlanDayStatus::Pending);
});

it('clears the history when the day is finally written', function () {
    $day = planDay(PlanDayStatus::Generating, 1, ['day.a: раз']);

    $day->markReady(CollectionId::fromString(Ulid::generate()));

    // A list of things wrong with a day that no longer exists is noise.
    expect($day->lastViolations())->toBe([])
        ->and($day->failReason())->toBeNull();
});

it('gives a spent day exactly one attempt back, and keeps what it learned', function () {
    $day = planDay(PlanDayStatus::Failed, PlanDay::MAX_ATTEMPTS, ['day.a: раз']);

    expect($day->claim())->toBeFalse();

    $day->reopenForRetry();

    expect($day->claim())->toBeTrue()
        ->and($day->generationAttempts())->toBe(PlanDay::MAX_ATTEMPTS)
        // ONE attempt, not a fresh budget: the next failure is terminal again.
        ->and($day->lastViolations())->toBe(['day.a: раз']);

    $day->markFailed('и снова', []);
    expect($day->status())->toBe(PlanDayStatus::Failed)
        ->and($day->claim())->toBeFalse();
});

it('refuses to reopen a day that is not spent', function (PlanDayStatus $status) {
    // A `ready` day reopened would throw its collection away; a `pending` one needs nothing.
    expect(fn () => planDay($status, 1)->reopenForRetry())->toThrow(InvalidPlanTransition::class);
})->with([PlanDayStatus::Pending, PlanDayStatus::Ready, PlanDayStatus::Done]);
