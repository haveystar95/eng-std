<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\ExerciseSelector;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

beforeEach(function () {
    $this->selector = new ExerciseSelector();
    $this->phase1 = new EnabledModes([ExerciseMode::MultipleChoice, ExerciseMode::WordBank, ExerciseMode::Typing]);
    $this->all = new EnabledModes([
        ExerciseMode::MultipleChoice, ExerciseMode::WordBank,
        ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Cloze,
    ]);
});

function atState(LearningState $state, int $reps = 0): TermProgress
{
    return TermProgress::reconstitute(
        UserId::generate(), TermId::generate(), $state, 2.5, 10, null, reps: $reps, lapses: 0, lastReviewedAt: null,
    );
}

it('introduces a new term (reps 0) with multiple choice', function () {
    expect($this->selector->select(atState(LearningState::New), $this->phase1))->toBe(ExerciseMode::MultipleChoice);
});

it('recognises any reps-0 card first, whatever the answer shape', function () {
    // The first meeting is recognition regardless of word count — a multi-word reps-0 card is still MC.
    expect($this->selector->select(atState(LearningState::Learning, 0), $this->phase1, answerWordCount: 3))
        ->toBe(ExerciseMode::MultipleChoice);
});

it('produces a multi-word term (reps ≥ 1) from a word bank', function () {
    expect($this->selector->select(atState(LearningState::Learning, 1), $this->phase1, answerWordCount: 3))
        ->toBe(ExerciseMode::WordBank);
});

it('produces a single-word term (reps ≥ 1) by typing — variety from the second meeting', function () {
    // Was multiple_choice under the old state-only ladder; now a produced single word is typed.
    expect($this->selector->select(atState(LearningState::Learning, 1), $this->phase1, answerWordCount: 1))
        ->toBe(ExerciseMode::Typing);
});

it('produces a relearning term (reps ≥ 1) rather than recognising it again', function () {
    // A lapsed term has always been produced before (reps ≥ 1) → production, not recognition.
    expect($this->selector->select(atState(LearningState::Relearning, 4), $this->phase1, answerWordCount: 1))
        ->toBe(ExerciseMode::Typing);
    expect($this->selector->select(atState(LearningState::Relearning, 4), $this->phase1, answerWordCount: 2))
        ->toBe(ExerciseMode::WordBank);
});

it('always checks a due known term in typing', function () {
    expect($this->selector->select(atState(LearningState::Known), $this->phase1))->toBe(ExerciseMode::Typing);
});

it('rotates review modes deterministically by the review counter', function () {
    expect($this->selector->select(atState(LearningState::Review, 0), $this->all))->toBe(ExerciseMode::Typing)
        ->and($this->selector->select(atState(LearningState::Review, 1), $this->all))->toBe(ExerciseMode::Listening)
        ->and($this->selector->select(atState(LearningState::Review, 2), $this->all))->toBe(ExerciseMode::Cloze)
        ->and($this->selector->select(atState(LearningState::Review, 3), $this->all))->toBe(ExerciseMode::Typing);
});

it('degrades review to the only enabled review mode in phase 1', function () {
    // Among typing/listening/cloze, only typing is on — every review card is typing.
    expect($this->selector->select(atState(LearningState::Review, 5), $this->phase1))->toBe(ExerciseMode::Typing);
});

it('falls back to an enabled mode when the preferred one is switched off', function () {
    $onlyMc = new EnabledModes([ExerciseMode::MultipleChoice]);

    // A produced single word prefers typing, which is off → fall back to the one enabled mode.
    expect($this->selector->select(atState(LearningState::Learning, 1), $onlyMc, answerWordCount: 1))
        ->toBe(ExerciseMode::MultipleChoice);
});
