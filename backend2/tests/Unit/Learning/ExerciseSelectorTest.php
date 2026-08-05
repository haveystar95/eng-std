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

it('introduces a new term with multiple choice', function () {
    expect($this->selector->select(atState(LearningState::New), $this->phase1))->toBe(ExerciseMode::MultipleChoice);
});

it('assembles a multi-word learning term from a word bank', function () {
    expect($this->selector->select(atState(LearningState::Learning), $this->phase1, answerWordCount: 3))
        ->toBe(ExerciseMode::WordBank);
});

it('keeps a single-word learning term on multiple choice — nothing to assemble', function () {
    expect($this->selector->select(atState(LearningState::Learning), $this->phase1, answerWordCount: 1))
        ->toBe(ExerciseMode::MultipleChoice);
});

it('sends a relearning term back to multiple choice', function () {
    expect($this->selector->select(atState(LearningState::Relearning), $this->phase1))->toBe(ExerciseMode::MultipleChoice);
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
    $onlyTyping = new EnabledModes([ExerciseMode::Typing]);

    // Learning prefers word_bank, which is off → fall back to the one enabled mode.
    expect($this->selector->select(atState(LearningState::Learning), $onlyTyping))->toBe(ExerciseMode::Typing);
});
