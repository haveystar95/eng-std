<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\Service\ModePassport;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;

dataset('mode passport floors', [
    'intro — new'            => [ExerciseMode::Intro, Acquisition::New],
    'multiple_choice — learning' => [ExerciseMode::MultipleChoice, Acquisition::Learning],
    'word_bank — graduated'   => [ExerciseMode::WordBank, Acquisition::Graduated],
    'cloze — graduated'       => [ExerciseMode::Cloze, Acquisition::Graduated],
    'scramble — graduated'    => [ExerciseMode::Scramble, Acquisition::Graduated],
    'pick_correct — graduated' => [ExerciseMode::PickCorrect, Acquisition::Graduated],
    'typing — graduated'      => [ExerciseMode::Typing, Acquisition::Graduated],
    'listening — graduated'   => [ExerciseMode::Listening, Acquisition::Graduated],
    'dictation — graduated'   => [ExerciseMode::Dictation, Acquisition::Graduated],
    'speaking — graduated'    => [ExerciseMode::Speaking, Acquisition::Graduated],
]);

it('derives the constructive minimum phase per mode', function (ExerciseMode $mode, Acquisition $expected) {
    expect(ModePassport::floorFor($mode))->toBe($expected);
})->with('mode passport floors');

// The consistency guard the наряд asked for: the floor, read as a rung through the SAME function
// a stored rule's own threshold is, must be exactly what LearningLadder itself computes for that
// phase at the ladder's own starting point. If LearningLadder ever adds a phase or renumbers a
// rung without this map being revisited, this is the test that catches the drift.
it('reads the floor as a rung that agrees with LearningLadder::stepFor, for every mode', function (ExerciseMode $mode, Acquisition $expected) {
    $viaLadder = LearningLadder::stepFor($expected, 0, LearningLadder::FIRST_LADDER_STEP) ?? LearningLadder::STEP_ASSEMBLY;

    expect(ModePassport::floorStepFor($mode))->toBe($viaLadder);
})->with('mode passport floors');

it('covers every exercise mode with no gaps', function () {
    foreach (ExerciseMode::cases() as $mode) {
        expect(fn () => ModePassport::floorFor($mode))->not->toThrow(Throwable::class);
    }
});

it('accepts a threshold at or above the floor and refuses one below it', function () {
    expect(ModePassport::meetsFloor(ExerciseMode::Speaking, Acquisition::Graduated))->toBeTrue()
        ->and(ModePassport::meetsFloor(ExerciseMode::Speaking, Acquisition::Learning))->toBeFalse()
        ->and(ModePassport::meetsFloor(ExerciseMode::Speaking, Acquisition::New))->toBeFalse()
        ->and(ModePassport::meetsFloor(ExerciseMode::MultipleChoice, Acquisition::Learning))->toBeTrue()
        ->and(ModePassport::meetsFloor(ExerciseMode::MultipleChoice, Acquisition::Graduated))->toBeTrue()
        ->and(ModePassport::meetsFloor(ExerciseMode::MultipleChoice, Acquisition::New))->toBeFalse()
        ->and(ModePassport::meetsFloor(ExerciseMode::Intro, Acquisition::New))->toBeTrue();
});

it('gives speaking the наряд\'s own phrasing, and a non-empty reason for every mode', function () {
    expect(ModePassport::reasonFor(ExerciseMode::Speaking))
        ->toBe('speaking — режим воспоминания, до выпуска слову нечего вспоминать.');

    foreach (ExerciseMode::cases() as $mode) {
        expect(ModePassport::reasonFor($mode))->not->toBe('');
    }
});
