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

it('rotates review modes deterministically by the review counter (example-backed)', function () {
    $rev = fn (int $reps) => $this->selector->select(atState(LearningState::Review, $reps), $this->all, clozeable: true);
    expect($rev(0))->toBe(ExerciseMode::Typing)
        ->and($rev(1))->toBe(ExerciseMode::Listening)
        ->and($rev(2))->toBe(ExerciseMode::Cloze)
        ->and($rev(3))->toBe(ExerciseMode::Typing);
});

it('leads the reps ≥ 1 production rotation with the base mode, then fans out (offset (reps-1))', function () {
    // Single word: base is typing. Second meeting (reps 1) is typing (TLv2), then listening, then cloze.
    $single = fn (int $reps) => $this->selector->select(atState(LearningState::Learning, $reps), $this->all, answerWordCount: 1, clozeable: true);
    expect($single(1))->toBe(ExerciseMode::Typing)
        ->and($single(2))->toBe(ExerciseMode::Listening)
        ->and($single(3))->toBe(ExerciseMode::Cloze)
        ->and($single(4))->toBe(ExerciseMode::Typing);

    // Multi-word: base is word_bank; the rotation leads with it.
    $multi = fn (int $reps) => $this->selector->select(atState(LearningState::Learning, $reps), $this->all, answerWordCount: 3, clozeable: true);
    expect($multi(1))->toBe(ExerciseMode::WordBank)
        ->and($multi(2))->toBe(ExerciseMode::Listening)
        ->and($multi(3))->toBe(ExerciseMode::Cloze);
});

it('never offers cloze when the term has no usable example (falls through to the typed ladder)', function () {
    // clozeable defaults false → cloze is dropped from every rotation.
    $single = fn (int $reps) => $this->selector->select(atState(LearningState::Learning, $reps), $this->all, answerWordCount: 1);
    expect($single(1))->toBe(ExerciseMode::Typing)
        ->and($single(2))->toBe(ExerciseMode::Listening)
        ->and($single(3))->toBe(ExerciseMode::Typing); // wraps typing/listening — cloze never appears

    // Review with no example: typing/listening only.
    $rev = fn (int $reps) => $this->selector->select(atState(LearningState::Review, $reps), $this->all);
    expect($rev(0))->toBe(ExerciseMode::Typing)
        ->and($rev(1))->toBe(ExerciseMode::Listening)
        ->and($rev(2))->toBe(ExerciseMode::Typing);
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

// ── free practice: fan across ALL applicable modes (not the reps ladder) ─────────

/** @return list<ExerciseMode> */
function practiceRun(ExerciseSelector $sel, EnabledModes $enabled, int $wordCount, bool $clozeable, int $rounds = 10): array
{
    // rotation is card-index + a per-term offset in production; here we sweep indices to see the fan.
    $out = [];
    for ($i = 0; $i < $rounds; $i++) {
        $out[] = $sel->selectForPractice($enabled, $i, $wordCount, $clozeable);
    }

    return $out;
}

it('practice fans a clozeable multi-word term across every enabled mode', function () {
    $modes = practiceRun($this->selector, $this->all, wordCount: 3, clozeable: true);

    expect(array_unique(array_map(fn (ExerciseMode $m): string => $m->value, $modes)))
        ->toEqualCanonicalizing(['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze']);
});

it('practice never assembles a single word (no word_bank) but still fans the rest', function () {
    $modes = practiceRun($this->selector, $this->all, wordCount: 1, clozeable: true);
    $seen = array_map(fn (ExerciseMode $m): string => $m->value, $modes);

    expect($seen)->not->toContain('word_bank')
        ->and(array_unique($seen))->toEqualCanonicalizing(['multiple_choice', 'typing', 'listening', 'cloze']);
});

it('practice never offers cloze without a usable example', function () {
    $modes = practiceRun($this->selector, $this->all, wordCount: 3, clozeable: false);
    $seen = array_map(fn (ExerciseMode $m): string => $m->value, $modes);

    expect($seen)->not->toContain('cloze')
        ->and(array_unique($seen))->toEqualCanonicalizing(['multiple_choice', 'word_bank', 'typing', 'listening']);
});

it('practice round-robins deterministically by the rotation seed', function () {
    // Same seed → same mode (reproducible); consecutive seeds walk the applicable set in order.
    $a = $this->selector->selectForPractice($this->all, 0, 3, true);
    $b = $this->selector->selectForPractice($this->all, 0, 3, true);
    expect($a)->toBe($b);

    // A negative seed (a large per-term offset can push it) still maps into range.
    expect($this->selector->selectForPractice($this->all, -1, 3, true))->toBeInstanceOf(ExerciseMode::class);
});

it('practice honours the enabled set — a phase-1 config never yields listening or cloze', function () {
    $modes = practiceRun($this->selector, $this->phase1, wordCount: 3, clozeable: true);
    $seen = array_unique(array_map(fn (ExerciseMode $m): string => $m->value, $modes));

    expect($seen)->toEqualCanonicalizing(['multiple_choice', 'word_bank', 'typing']);
});
