<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\Mastery;
use App\Modules\Learning\Domain\ValueObject\LearningState;

dataset('mastery', [
    'review at the 21-day threshold is mastered' => [LearningState::Review, 21, true],
    'review just below the threshold is not'     => [LearningState::Review, 20, false],
    'review well past the threshold is mastered' => [LearningState::Review, 365, true],
    'known is mastered regardless of interval'   => [LearningState::Known, 0, true],
    'known with a long interval is mastered'     => [LearningState::Known, 999, true],
    'new is never mastered'                      => [LearningState::New, 0, false],
    'learning is never mastered'                 => [LearningState::Learning, 30, false],
    'relearning is never mastered'               => [LearningState::Relearning, 40, false],
]);

it('has one definition of mastered used everywhere', function (LearningState $state, int $interval, bool $expected) {
    expect(Mastery::isMastered($state, $interval))->toBe($expected);
})->with('mastery');
