<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\AnswerGrader;
use App\Modules\Learning\Domain\ValueObject\Answer;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ExpectedAnswer;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LatencyBaseline;

beforeEach(fn () => $this->grader = new AnswerGrader());

function answerKey(array $accepted, bool $isPhrase = false): ExpectedAnswer
{
    return new ExpectedAnswer($accepted, $isPhrase);
}

it('grades a correct typed answer at unknown speed as good', function () {
    $grade = $this->grader->grade(new Answer('withdraw'), ExerciseMode::Typing, answerKey(['withdraw']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Good);
});

it('awards easy for a fast, clean, produced answer', function () {
    // 1500 ms is under the word fast-default (2000) → fast; typing is a production mode.
    $grade = $this->grader->grade(new Answer('withdraw', latencyMs: 1500), ExerciseMode::Typing, answerKey(['withdraw']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Easy);
});

it('never awards easy in a recognition mode, however fast', function () {
    $grade = $this->grader->grade(new Answer('withdraw', latencyMs: 50), ExerciseMode::MultipleChoice, answerKey(['withdraw']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Good);
});

it('drops a hinted answer to hard', function () {
    $grade = $this->grader->grade(new Answer('withdraw', usedHint: true, latencyMs: 1000), ExerciseMode::Typing, answerKey(['withdraw']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Hard);
});

it('drops a slow answer to hard, relative to the personal per-mode median', function () {
    // 2000 ms is well over 1000 * 1.6 → slow.
    $grade = $this->grader->grade(new Answer('withdraw', latencyMs: 2000), ExerciseMode::Typing, answerKey(['withdraw']), LatencyBaseline::median(1000));

    expect($grade)->toBe(Grade::Hard);
});

it('accepts an accepted synonym at full grade', function () {
    $grade = $this->grader->grade(new Answer('shore'), ExerciseMode::Typing, answerKey(['bank', 'shore']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Good);
});

it('ignores letter case', function () {
    $grade = $this->grader->grade(new Answer('BANK'), ExerciseMode::Typing, answerKey(['bank']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Good);
});

it('normalizes punctuation and spacing for a phrase', function () {
    $grade = $this->grader->grade(
        new Answer('  I need,  to withdraw cash! '),
        ExerciseMode::Typing,
        answerKey(['I need to withdraw cash'], isPhrase: true),
        LatencyBaseline::insufficient(),
    );

    expect($grade)->toBe(Grade::Good);
});

it('treats a leading article as optional in both directions', function () {
    expect($this->grader->grade(new Answer('bank'), ExerciseMode::Typing, answerKey(['the bank']), LatencyBaseline::insufficient()))->toBe(Grade::Good)
        ->and($this->grader->grade(new Answer('the bank'), ExerciseMode::Typing, answerKey(['bank']), LatencyBaseline::insufficient()))->toBe(Grade::Good);
});

it('expands common contractions so "I\'d like" matches "I would like"', function () {
    $grade = $this->grader->grade(
        new Answer("I'd like to withdraw"),
        ExerciseMode::Typing,
        answerKey(['I would like to withdraw'], isPhrase: true),
        LatencyBaseline::insufficient(),
    );

    expect($grade)->toBe(Grade::Good);
});

it('accepts a single-character typo on a long word but caps it at hard', function () {
    $grade = $this->grader->grade(new Answer('withdaw'), ExerciseMode::Typing, answerKey(['withdraw']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Hard);
});

// Found on the device: pick_correct offered "Could you takes a photo of us…" against "Could you take
// a photo of us…" — one character apart — and the typo stage marked the BROKEN sentence correct,
// scheduling the term as learned. Typo leniency forgives typing; a tapped answer has no typing in it.
it('does not forgive a one-character difference when the answer was PICKED, not typed', function () {
    $right = 'Could you take a photo of us in front of the monument?';
    $wrong = 'Could you takes a photo of us in front of the monument?';

    $grade = $this->grader->grade(
        new Answer($wrong), ExerciseMode::PickCorrect, answerKey([$right], isPhrase: true), LatencyBaseline::insufficient(),
    );

    expect($grade)->toBe(Grade::Again);
});

it('still forgives that same difference when it WAS typed', function () {
    $grade = $this->grader->grade(
        new Answer('withdrow'), ExerciseMode::Typing, answerKey(['withdraw']), LatencyBaseline::insufficient(),
    );

    expect($grade)->toBe(Grade::Hard);
});

it('does not forgive a typo on multiple_choice or the assembling modes either', function () {
    foreach ([ExerciseMode::MultipleChoice, ExerciseMode::WordBank, ExerciseMode::Scramble] as $mode) {
        expect($this->grader->grade(
            new Answer('withdrow'), $mode, answerKey(['withdraw']), LatencyBaseline::insufficient(),
        ))->toBe(Grade::Again);
    }
});

it('rejects a one-letter difference on a short word as wrong', function () {
    $grade = $this->grader->grade(new Answer('cut'), ExerciseMode::Typing, answerKey(['cat']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Again);
});

it('marks a genuinely wrong answer as again', function () {
    $grade = $this->grader->grade(new Answer('deposit'), ExerciseMode::Typing, answerKey(['withdraw']), LatencyBaseline::insufficient());

    expect($grade)->toBe(Grade::Again);
});

/**
 * Unicode is the grader's problem before spelling is (DECISIONS п. 87).
 *
 * The learner does not choose which byte sequence their keyboard emits. «știi» typed with a cedilla
 * `ş` and «știi» typed with a comma-below `ș` are the same word to everyone except a byte
 * comparison, and a card that says «не то» to a Romanian speaker for writing Romanian is teaching
 * them nothing. One fold, in `LexicalNormalizer::canonicalize()`, and the whole class is gone.
 */
it('forgives the cedilla spelling of a comma-below Romanian key', function () {
    $key = answerKey(["\u{0219}tiu"]);   // știu, canonical

    expect($this->grader->grade(new Answer("\u{015F}tiu"), ExerciseMode::Typing, $key, LatencyBaseline::insufficient()))
        ->toBe(Grade::Good)
        // …and the other direction, because an older row may still hold the cedilla form.
        ->and($this->grader->grade(
            new Answer("\u{0219}tiu"), ExerciseMode::Typing, answerKey(["\u{015F}tiu"]), LatencyBaseline::insufficient(),
        ))->toBe(Grade::Good);
});

it('forgives the decomposed spelling of the same letter', function () {
    // s + COMBINING COMMA BELOW against the precomposed ș: identical on screen, different bytes.
    $grade = $this->grader->grade(
        new Answer("s\u{0326}tiu"), ExerciseMode::Typing, answerKey(["\u{0219}tiu"]), LatencyBaseline::insufficient(),
    );

    expect($grade)->toBe(Grade::Good);
});

it('accepts ss for ß and oe for œ', function () {
    expect($this->grader->grade(new Answer('Strasse'), ExerciseMode::Typing, answerKey(['Straße']), LatencyBaseline::insufficient()))
        ->toBe(Grade::Good)
        ->and($this->grader->grade(new Answer('coeur'), ExerciseMode::Typing, answerKey(['cœur']), LatencyBaseline::insufficient()))
        ->toBe(Grade::Good);
});
