<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\AnswerGrader;
use App\Modules\Learning\Domain\ValueObject\Answer;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ExpectedAnswer;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LatencyBaseline;
use App\Modules\Learning\Domain\ValueObject\MatchPolicy;
use App\Modules\Shared\Domain\Service\SpokenWordBoundary;

/**
 * A SPACE IS THE RECOGNISER'S GUESS — {@see SpokenWordBoundary}.
 *
 * The live case is first and verbatim: owner's plan `01M1HZF4…`, day 1, card «I see, without
 * utilities.» read aloud correctly, transcribed «I see, withoututilities», graded `again` three
 * sittings running (`reviews`, 02.09 21:42, 21:50, 21:55) — the line's stage-A checklist could not
 * close, and the scheduler was handed a lapse for a reading that was right.
 */

it('accepts the live reading whose recogniser glued two words together', function () {

    expect(learningCovers('I see, withoututilities', 'I see, without utilities.'))->toBeTrue()
        ->and(learningRatio('I see, withoututilities', 'I see, without utilities.'))->toBe(1.0);
});

it('accepts a glue in the other direction — one expected word heard as two', function () {

    expect(learningCovers('is the listing still avail able', 'Is the listing still available?'))->toBeTrue();
});

it('still refuses a reading that dropped a word of its own', function () {

    // «пропуск смысловых слов остаётся честным отказом»: three of six words said, and no boundary
    // anywhere explains the other three.
    expect(learningCovers('could you write please', 'Could you write it down, please?'))->toBeFalse();
});

it('does not invent a word the sentence never asked for', function () {
    $boundary = new SpokenWordBoundary();

    // «carpet» is not «car» + «pet» here: neither piece is a word of the expected sentence, so the
    // token survives whole and fails to match, which is the honest outcome.
    expect($boundary->align(['carpet'], ['the', 'red', 'car']))->toBe(['carpet']);
});

it('grades a glued two-word TERM correct on the equality path', function () {
    $grader = new AnswerGrader();

    // «without utilities» is a `chunks` card — two words, under the coverage threshold's length, so
    // it is compared whole. The recogniser glues it exactly as readily as it glues a sentence.
    $grade = $grader->grade(
        new Answer('withoututilities', latencyMs: 3000),
        ExerciseMode::Speaking,
        new ExpectedAnswer(['without utilities'], isPhrase: true, policy: MatchPolicy::Exact),
        LatencyBaseline::insufficient(),
    );

    expect($grade)->not->toBe(Grade::Again);
});

it('gives a typed answer no boundary tolerance — the spaces there were typed by the learner', function () {
    $grader = new AnswerGrader();

    $grade = $grader->grade(
        new Answer('withoututilities', latencyMs: 3000),
        ExerciseMode::Typing,
        new ExpectedAnswer(['without utilities'], isPhrase: true, policy: MatchPolicy::Exact),
        LatencyBaseline::insufficient(),
    );

    // `hard` and not `good`: typing reaches this through the TYPO stage, which has always
    // forgiven one character (a missing space is one) at a ceiling of «Почти». What must not
    // happen is the speaking path's full grade, and it does not.
    expect($grade)->toBe(Grade::Hard);
});

it('keeps the apostrophe fold (Д-32) intact through the re-cut', function () {

    // «He's five years old.» folds to «hes five years old» on both sides; nothing about the
    // boundary pass may split that back into «he s».
    expect(learningCovers('hes five years old', "He's five years old."))->toBeTrue()
        ->and(learningCovers("he's five years old", "Hes five years old."))->toBeTrue();
});
