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

it('charges the repair call to the day`s budget, so a repaired-and-still-broken day is spent', function () {
    // `generationAttempts` is the MONEY (п. 199, первая половина). A run that spent a day call AND
    // a P2R call spent two, and there is no third answer to buy — the day is `failed`, not
    // `pending` with an attempt that does not exist.
    $day = planDay(PlanDayStatus::Generating, 1);

    $day->markFailed('день и починка', ['day.a: раз'], paidCalls: 2);

    expect($day->generationAttempts())->toBe(2)
        ->and($day->status())->toBe(PlanDayStatus::Failed)
        ->and($day->claim())->toBeFalse();
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
