<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\ChipShuffler;
use App\Modules\Learning\Domain\Service\ExerciseSelector;
use App\Modules\Learning\Domain\Service\PlayabilityAssessor;
use App\Modules\Learning\Domain\Service\SentenceTokenizer;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

beforeEach(function () {
    $this->assess = new PlayabilityAssessor(new ChipShuffler(), new SentenceTokenizer());
    $this->selector = new ExerciseSelector();
});

/** Does the assessed term admit a dictation card? */
function dictates(PlayabilityAssessor $assess, string $answer, ?string $example, ?string $translation = 'Перевод.'): bool
{
    return $assess->assess($answer, $example, $translation)->supports(ExerciseMode::Dictation);
}

it('admits a sentence inside the 4…10 window', function () {
    expect(dictates($this->assess, 'reservation', 'I have a reservation for tonight.'))->toBeTrue(); // 6
});

it('admits exactly at both edges and refuses just outside', function () {
    $of = fn (int $words): string => implode(' ', array_fill(0, $words, 'word')).'.';

    expect(dictates($this->assess, 'word', $of(3)))->toBeFalse()
        ->and(dictates($this->assess, 'word', $of(4)))->toBeTrue()   // MIN
        ->and(dictates($this->assess, 'word', $of(10)))->toBeTrue()  // MAX
        ->and(dictates($this->assess, 'word', $of(11)))->toBeFalse();
});

it('stops earlier than scramble — typing by ear costs more than dropping tiles', function () {
    $eleven = implode(' ', array_fill(0, 11, 'word')).'.';
    $playable = $this->assess->assess('word', $eleven, 'Перевод.');

    expect($playable->supports(ExerciseMode::Scramble))->toBeTrue()
        ->and($playable->supports(ExerciseMode::Dictation))->toBeFalse();
});

it('needs no translation — the task is the audio, not a written cue', function () {
    $sentence = 'I have a reservation for tonight.';

    // Exactly where it parts company with scramble, which has nothing to ask without one.
    expect(dictates($this->assess, 'reservation', $sentence, null))->toBeTrue()
        ->and($this->assess->assess('reservation', $sentence, null)->supports(ExerciseMode::Scramble))->toBeFalse();
});

it('refuses a term with no example — there is no sentence to dictate', function () {
    expect(dictates($this->assess, 'front desk', null))->toBeFalse()
        ->and(dictates($this->assess, 'front desk', ''))->toBeFalse();
});

it('refuses an example that is merely the term — that is listening, twice', function () {
    expect(dictates($this->assess, 'Nice to meet you', 'Nice to meet you.'))->toBeFalse()
        ->and(dictates($this->assess, 'Nice to meet you', 'Nice to meet you, I am Denis.'))->toBeTrue();
});

// ── ladder ───────────────────────────────────────────────────────────────────

it('is a rung-5 trainer only — a pair below it never meets it', function () {
    $enabled = new EnabledModes([ExerciseMode::MultipleChoice, ExerciseMode::Typing, ExerciseMode::Dictation]);
    $playable = $this->assess->assess('reservation', 'I have a reservation for tonight.', 'Перевод.');

    $below = fn (LearningState $state, int $reps) => $this->selector->select(
        TermProgress::reconstitute(UserId::generate(), TermId::generate(), $state, 2.5, 10, null, reps: $reps, lapses: 0, lastReviewedAt: null, successfulReviews: $reps),
        $enabled,
        $playable,
        shippedMatrix(),
    );

    // Every rung below 5, on both scheduler branches. The rung is what gates it now — not the
    // SM-2 state, which the admission matrix deliberately cannot see.
    for ($reps = 0; $reps < \App\Modules\Learning\Domain\Service\LearningLadder::DICTATION_MIN_SUCCESSES; $reps++) {
        expect($below(LearningState::Learning, $reps))->not->toBe(ExerciseMode::Dictation)
            ->and($below(LearningState::Review, $reps))->not->toBe(ExerciseMode::Dictation);
    }
});

it('takes its turn in the review rotation, last of the rungs', function () {
    $enabled = new EnabledModes([ExerciseMode::Typing, ExerciseMode::Dictation]);
    $playable = $this->assess->assess('reservation', 'I have a reservation for tonight.', 'Перевод.');

    $review = fn (int $reps) => $this->selector->select(
        TermProgress::reconstitute(UserId::generate(), TermId::generate(), LearningState::Review, 2.5, 10, null, reps: $reps, lapses: 0, lastReviewedAt: null,
            // The rotation reads `reps`, the rung reads the successes. A pair that has never
            // got one wrong has them equal, which is the case this rotation is written for.
            successfulReviews: $reps),
        $enabled,
        $playable,
        shippedMatrix(),
    );

    // At rung 5 only typing and dictation are on, so the rotation is those two, in ladder order.
    expect($review(6))->toBe(ExerciseMode::Typing)
        ->and($review(7))->toBe(ExerciseMode::Dictation)
        ->and($review(8))->toBe(ExerciseMode::Typing);
});

it('is never dealt while it is switched off, however well the term fits', function () {
    // The release rule in one assertion: the mode exists in code, the term qualifies, and the
    // learner still never sees it until someone turns it on.
    $off = new EnabledModes([ExerciseMode::Typing, ExerciseMode::Listening]);
    $playable = $this->assess->assess('reservation', 'I have a reservation for tonight.', 'Перевод.');

    expect($playable->supports(ExerciseMode::Dictation))->toBeTrue();
    for ($reps = 0; $reps <= 12; $reps++) {
        expect($this->selector->select(
            TermProgress::reconstitute(UserId::generate(), TermId::generate(), LearningState::Review, 2.5, 10, null, reps: $reps, lapses: 0, lastReviewedAt: null,
            // The rotation reads `reps`, the rung reads the successes. A pair that has never
            // got one wrong has them equal, which is the case this rotation is written for.
            successfulReviews: $reps),
            $off,
            $playable,
            shippedMatrix(),
        ))->not->toBe(ExerciseMode::Dictation);
    }
});

it('can earn easy — nothing was on the screen to lean on', function () {
    expect(ExerciseMode::Dictation->isProduction())->toBeTrue()
        ->and(ExerciseMode::Dictation->maxGrade())->toBe(\App\Modules\Learning\Domain\ValueObject\Grade::Easy)
        ->and(ExerciseMode::Dictation->gradesAgainstExample(null))->toBeTrue();
});
