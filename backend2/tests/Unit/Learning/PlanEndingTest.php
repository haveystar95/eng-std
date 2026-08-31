<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\EndPlan;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\ValueObject\PlanEnding;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Learning\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** A plan with nothing generated — the state every ending below is reached from. */
function draftPlan(): LearningPlan
{
    return LearningPlan::draft(
        id: PlanId::fromString(Ulid::generate()),
        userId: UserId::fromString(Ulid::generate()),
        title: 'Собеседование',
        goalText: 'Онлайн-собеседование PHP-разработчика',
        targetLang: new LanguageCode('en'),
        supportLang: new LanguageCode('ru'),
        level: PlanLevel::Conversational,
        eventDate: new DateTimeImmutable('2026-09-05 00:00:00'),
        minutesPerDay: 20,
    );
}

/**
 * The trap this type exists to close.
 *
 * `'abandoned'` is the STATUS. `'abandon'` is the ACTION. While the action was a string they were
 * one letter-group apart, and the handler's `default` arm turned the first into COMPLETE — a plan
 * the learner walked away from, filed in their history as finished. The compiler stops that now;
 * this is what stops someone re-opening it by adding a case that reads like a status.
 */
it('is not addressable by a status — the two vocabularies stay apart', function (string $status) {
    expect(PlanEnding::tryFrom($status))->toBeNull();
})->with([
    PlanStatus::Abandoned->value,
    PlanStatus::Completed->value,
    PlanStatus::Paused->value,
]);

it('carries a reason only when the ending was not the learner`s own', function () {
    // «Я передумал» needs no column: a person tapping «отказаться» has their reason and it is not
    // the app's business. The tag is for the endings nobody chose — a prompt version whose
    // skeletons can no longer be read, a live run that left a plan broken.
    $plan = draftPlan();
    $plan->abandon();
    expect($plan->status())->toBe(PlanStatus::Abandoned)
        ->and($plan->abandonReason())->toBeNull();

    $onBehalf = draftPlan();
    $onBehalf->abandon('prompt_v0_2_1_run');
    expect($onBehalf->abandonReason())->toBe('prompt_v0_2_1_run');
});

it('refuses a reason on an ending that has nowhere to store it', function () {
    // Pausing and completing do not touch the column, so a reason handed to either would be
    // accepted, dropped, and believed by whoever wrote it.
    expect(fn () => new EndPlan(
        PlanId::fromString(Ulid::generate()),
        UserId::fromString(Ulid::generate()),
        PlanEnding::Pause,
        'prompt_v0_2_1_run',
    ))->toThrow(InvalidArgumentException::class, 'pause');
});

it('says which of the three keeps the plan`s hold on the words', function () {
    // Pause means «я вернусь»; the other two let the words go back into the pool as ordinary words.
    expect(PlanEnding::Pause->keepsHold())->toBeTrue()
        ->and(PlanEnding::Abandon->keepsHold())->toBeFalse()
        ->and(PlanEnding::Complete->keepsHold())->toBeFalse();
});
