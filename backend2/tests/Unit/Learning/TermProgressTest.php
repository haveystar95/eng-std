<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

it('starts a term as new with default ease and nothing due', function () {
    $progress = TermProgress::start(UserId::generate(), TermId::generate());

    expect($progress->state())->toBe(LearningState::New)
        ->and($progress->isNew())->toBeTrue()
        ->and($progress->easeFactor())->toBe(2.50)
        ->and($progress->intervalDays())->toBe(0)
        ->and($progress->reps())->toBe(0)
        ->and($progress->lapses())->toBe(0)
        ->and($progress->dueAt())->toBeNull()
        ->and($progress->lastReviewedAt())->toBeNull();
});

it('drops a known term to learning when its verification fails, keeping history', function () {
    $now = new DateTimeImmutable('2026-07-30T09:00:00Z');
    $known = TermProgress::reconstitute(
        UserId::generate(), TermId::generate(), LearningState::Known, 2.5, 0, $now, reps: 4, lapses: 2, lastReviewedAt: null,
    );

    $failed = $known->failVerification($now);

    // Explicit known → learning (never SM-2's review → relearning path), due now, history kept.
    expect($failed->state())->toBe(LearningState::Learning)
        ->and($failed->intervalDays())->toBe(0)
        ->and($failed->dueAt())->toEqual($now)
        ->and($failed->reps())->toBe(4)
        ->and($failed->lapses())->toBe(2);
});

it('keeps a term known and pushes the next check out when verification passes', function () {
    $now = new DateTimeImmutable('2026-07-30T09:00:00Z');
    $known = TermProgress::knownFromTriage(UserId::generate(), TermId::generate(), $now);

    $passed = $known->passVerification($now, 90);

    expect($passed->state())->toBe(LearningState::Known)
        ->and($passed->dueAt())->toEqual($now->modify('+90 days'));
});
