<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\ExerciseSelector;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\TermPlayability;

/**
 * `pick_correct`'s gate is unlike every other mode's: it depends on CONTENT THE STANOK WROTE, not on
 * the term's own shape. Two wrong sentences must exist, and a proofreader deleting a bad distractor
 * takes the mode away from that term again. These tests pin that, plus the release rule.
 */
function pickCorrectPlayability(int $distractors = 2, bool $hasTranslation = true, bool $exampleIsAnswer = false): TermPlayability
{
    return new TermPlayability(
        answerWordCount: 3,
        clozeable: true,
        exampleTokenCount: 7,
        hasExampleTranslation: $hasTranslation,
        exampleIsAnswer: $exampleIsAnswer,
        distractorCount: $distractors,
    );
}

it('admits a term with two validated distractors', function () {
    expect(pickCorrectPlayability(2)->supports(ExerciseMode::PickCorrect))->toBeTrue();
});

it('refuses a term with only one — a two-way choice is a coin toss', function () {
    expect(pickCorrectPlayability(1)->supports(ExerciseMode::PickCorrect))->toBeFalse();
});

it('refuses a term with none', function () {
    expect(pickCorrectPlayability(0)->supports(ExerciseMode::PickCorrect))->toBeFalse();
});

it('takes more than two happily', function () {
    expect(pickCorrectPlayability(3)->supports(ExerciseMode::PickCorrect))->toBeTrue();
});

it('refuses an untranslated example — the translation IS the question', function () {
    expect(pickCorrectPlayability(2, hasTranslation: false)->supports(ExerciseMode::PickCorrect))->toBeFalse();
});

it('refuses an example that is merely the term itself', function () {
    expect(pickCorrectPlayability(2, exampleIsAnswer: true)->supports(ExerciseMode::PickCorrect))->toBeFalse();
});

it('has no length window — three sentences are read, not assembled', function () {
    $long = new TermPlayability(
        answerWordCount: 3,
        clozeable: true,
        // Well past scramble's ceiling of 12; that limit is about a wall of tiles, which this mode
        // does not have.
        exampleTokenCount: 25,
        hasExampleTranslation: true,
        distractorCount: 2,
    );

    expect($long->supports(ExerciseMode::PickCorrect))->toBeTrue()
        ->and($long->supports(ExerciseMode::Scramble))->toBeFalse();
});

it('can never award easy — a three-way pick is the weakest evidence the app collects', function () {
    expect(ExerciseMode::PickCorrect->maxGrade())->toBe(Grade::Good)
        ->and(ExerciseMode::PickCorrect->isProduction())->toBeFalse();
});

it('grades against the example, because the committed answer IS a sentence', function () {
    expect(ExerciseMode::PickCorrect->gradesAgainstExample(null))->toBeTrue();
});

it('is never dealt while it is switched off, however well the term fits', function () {
    $selector = new ExerciseSelector();
    $enabled = new EnabledModes([ExerciseMode::Typing, ExerciseMode::Listening]);
    $playable = pickCorrectPlayability(3);

    // Walk the whole practice rotation: the mode must not appear at any seed.
    $dealt = [];
    for ($rotation = 0; $rotation < 8; $rotation++) {
        $dealt[] = $selector->selectForPractice($enabled, $rotation, $playable);
    }

    expect($dealt)->not->toContain(ExerciseMode::PickCorrect);
});

it('takes its turn in the practice fan once switched on', function () {
    $selector = new ExerciseSelector();
    $enabled = new EnabledModes([ExerciseMode::Typing, ExerciseMode::PickCorrect]);
    $playable = pickCorrectPlayability(2);

    $dealt = [];
    for ($rotation = 0; $rotation < 4; $rotation++) {
        $dealt[] = $selector->selectForPractice($enabled, $rotation, $playable);
    }

    expect($dealt)->toContain(ExerciseMode::PickCorrect);
});

it('is gated out of practice when the term has too few distractors, even when switched on', function () {
    $selector = new ExerciseSelector();
    $enabled = new EnabledModes([ExerciseMode::Typing, ExerciseMode::PickCorrect]);

    $dealt = [];
    for ($rotation = 0; $rotation < 4; $rotation++) {
        $dealt[] = $selector->selectForPractice($enabled, $rotation, pickCorrectPlayability(1));
    }

    expect($dealt)->not->toContain(ExerciseMode::PickCorrect);
});

// The ladder position is asserted in ExerciseSelectorTest, where the rotation helpers live — one
// place for ladder tests rather than two that can drift apart.
