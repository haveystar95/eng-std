<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\PlanEnding;
use App\Modules\Learning\Domain\ValueObject\PlanStatus;

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

it('says which of the three keeps the plan`s hold on the words', function () {
    // Pause means «я вернусь»; the other two let the words go back into the pool as ordinary words.
    expect(PlanEnding::Pause->keepsHold())->toBeTrue()
        ->and(PlanEnding::Abandon->keepsHold())->toBeFalse()
        ->and(PlanEnding::Complete->keepsHold())->toBeFalse();
});
