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
        pastViolations: $violations,
    );
}

it('accumulates the violations of every attempt, without duplicates', function () {
    $day = planDay(PlanDayStatus::Generating, 1);

    $day->markFailed('первая', ['day.a: раз', 'day.b: два']);
    $day->markFailed('вторая', ['day.b: два', 'day.c: три']);

    // The whole point: an answer told only what the LAST one broke re-breaks what it had fixed.
    expect($day->pastViolations())->toBe(['day.a: раз', 'day.b: два', 'day.c: три']);
});

it('clears the history when the day is finally written', function () {
    $day = planDay(PlanDayStatus::Generating, 1, ['day.a: раз']);

    $day->markReady(CollectionId::fromString(Ulid::generate()));

    // A list of things wrong with a day that no longer exists is noise.
    expect($day->pastViolations())->toBe([])
        ->and($day->failReason())->toBeNull();
});

it('gives a spent day exactly one attempt back, and keeps what it learned', function () {
    $day = planDay(PlanDayStatus::Failed, PlanDay::MAX_ATTEMPTS, ['day.a: раз']);

    expect($day->claim())->toBeFalse();

    $day->reopenForRetry();

    expect($day->claim())->toBeTrue()
        ->and($day->generationAttempts())->toBe(PlanDay::MAX_ATTEMPTS)
        // ONE attempt, not a fresh budget: the next failure is terminal again.
        ->and($day->pastViolations())->toBe(['day.a: раз']);

    $day->markFailed('и снова', []);
    expect($day->status())->toBe(PlanDayStatus::Failed)
        ->and($day->claim())->toBeFalse();
});

it('refuses to reopen a day that is not spent', function (PlanDayStatus $status) {
    // A `ready` day reopened would throw its collection away; a `pending` one needs nothing.
    expect(fn () => planDay($status, 1)->reopenForRetry())->toThrow(InvalidPlanTransition::class);
})->with([PlanDayStatus::Pending, PlanDayStatus::Ready, PlanDayStatus::Done]);
