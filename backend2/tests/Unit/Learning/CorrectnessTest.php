<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * There are two true statements about one answer, and they are not the same statement:
 *
 *   «did they get it right»           — anything but `again`. What the learner did, what the card
 *                                       told them, what the `is_correct` column stores.
 *   «did they recall it confidently»  — `good` or `easy`. A memory-strength signal for the
 *                                       scheduler, and the input FSRS will want.
 *
 * They lived under one name (`Grade::isCorrect`) and the daily projection used the strict one, so
 * the day's accuracy read 33% where it was 92% (QA-11). Pinned here so the two can never quietly
 * become one again.
 */
function graded(Grade $grade): Review
{
    return new Review(
        id: ReviewId::fromString((string) Ulid::generate()),
        userId: UserId::fromString((string) Ulid::generate()),
        termId: TermId::fromString((string) Ulid::generate()),
        grade: $grade,
        answeredAt: new DateTimeImmutable('2026-08-17T17:00:00Z'),
        clientSeq: 1,
        exerciseMode: ExerciseMode::MultipleChoice,
    );
}

it('counts a right-but-slow answer as RIGHT', function () {
    expect(graded(Grade::Hard)->isCorrect())->toBeTrue();
});

it('does not count a right-but-slow answer as CONFIDENT recall', function () {
    expect(Grade::Hard->isConfidentRecall())->toBeFalse();
});

it('agrees on the two ends and differs only on hard', function () {
    foreach ([Grade::Again, Grade::Hard, Grade::Good, Grade::Easy] as $grade) {
        $right = graded($grade)->isCorrect();
        $confident = $grade->isConfidentRecall();

        expect($confident && ! $right)->toBeFalse('confident recall is a SUBSET of right');
        expect($right === $confident)->toBe(
            $grade !== Grade::Hard,
            "the two questions must differ on hard and agree everywhere else ({$grade->value})",
        );
    }
});

it('reproduces the acceptance day: 11 right out of 12, not 4', function () {
    // The twelve grades the device recorded on 17.08, in order.
    $day = ['hard', 'good', 'hard', 'hard', 'again', 'hard', 'hard', 'hard', 'hard', 'good', 'good', 'good'];
    $reviews = array_map(static fn (string $g): Review => graded(Grade::from($g)), $day);

    $right = count(array_filter($reviews, static fn (Review $r): bool => $r->isCorrect()));
    $confident = count(array_filter($reviews, static fn (Review $r): bool => $r->grade->isConfidentRecall()));

    expect($right)->toBe(11, 'what the learner is shown, and what the projection now counts');
    expect($confident)->toBe(4, 'the number that used to be shown as «correct»');
});
