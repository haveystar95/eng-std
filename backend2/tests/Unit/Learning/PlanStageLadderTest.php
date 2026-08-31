<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanStageFact;

/**
 * The plan's ladder, judged the way it is used: a list of answers goes in, «which card does this
 * word owe next» comes out. No database, no clock — the standing is a projection over the review
 * log and this file is the contract that says so.
 */
beforeEach(fn () => $this->ladder = new PlanStageLadder());

/** Every trainer, so a test that is not about applicability does not have to think about it. */
function allModes(): array
{
    return ExerciseMode::cases();
}

function hit(ExerciseMode $mode, string $date): PlanStageFact
{
    return new PlanStageFact($mode, true, $date);
}

function miss(ExerciseMode $mode, string $date): PlanStageFact
{
    return new PlanStageFact($mode, false, $date);
}

// ── the stage order ───────────────────────────────────────────────────────────────────────────

it('deals a WORD its ladder: meet it, recognise it twice, say it — then the gap, then the typing', function () {
    expect(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_WORD))->toBe([
        ExerciseMode::Intro,
        ExerciseMode::MultipleChoice,
        ExerciseMode::MultipleChoice,
        ExerciseMode::Speaking,
    ])
        ->and(PlanStageLadder::modesOf(PlanStage::B, PlanStageLadder::KIND_WORD))
        ->toBe([ExerciseMode::Cloze, ExerciseMode::Typing])
        ->and(PlanStageLadder::modesOf(PlanStage::C, PlanStageLadder::KIND_WORD))
        ->toBe([ExerciseMode::Dictation, ExerciseMode::PickCorrect]);
});

it('deals a LINE its own ladder — and never typing, dictation, or a stage C at all', function () {
    // «My back has been hurting for a week.» typed letter for letter is a test of punctuation, not
    // of the ability the day promised. A line is put together, read aloud, and later said without
    // the text; there is nothing after that to take away.
    $a = PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_LINE);

    expect($a)->toHaveCount(4)
        ->and($a[0])->toBe(ExerciseMode::Intro)
        ->and($a[1])->toBe(ExerciseMode::MultipleChoice)
        ->and($a[3])->toBe(ExerciseMode::Speaking)
        ->and(PlanStageLadder::modesOf(PlanStage::B, PlanStageLadder::KIND_LINE))
        ->toBe([ExerciseMode::Cloze, ExerciseMode::Listening, ExerciseMode::Speaking])
        ->and(PlanStageLadder::modesOf(PlanStage::C, PlanStageLadder::KIND_LINE))->toBe([])
        ->and(PlanStageLadder::lastStageFor(PlanStageLadder::KIND_LINE))->toBe(PlanStage::B)
        ->and(PlanStageLadder::nextStageFor(PlanStage::B, PlanStageLadder::KIND_LINE))->toBeNull();

    foreach ([ExerciseMode::Typing, ExerciseMode::Dictation] as $never) {
        foreach (PlanStage::cases() as $stage) {
            expect(PlanStageLadder::modesOf($stage, PlanStageLadder::KIND_LINE))->not->toContain($never);
        }
    }
});

it('gives a CONNECTOR the word`s ladder exactly — the difference is where its gap is cut', function () {
    foreach (PlanStage::cases() as $stage) {
        expect(PlanStageLadder::modesOf($stage, PlanStageLadder::KIND_CHUNK))
            ->toBe(PlanStageLadder::modesOf($stage, PlanStageLadder::KIND_WORD));
    }
});

it('alternates the line`s assembly step by the PAIR, never by chance', function () {
    // The наряд is explicit: no random choice of exercise anywhere in a plan. Variety comes from
    // alternation, and alternation needs something that does not move — a counter that changed with
    // every answer would offer `scramble` to a checklist that ticked `word_bank`, and stage A would
    // never close.
    expect(PlanStageLadder::assemblyModeFor(0))->toBe(ExerciseMode::WordBank)
        ->and(PlanStageLadder::assemblyModeFor(1))->toBe(ExerciseMode::Scramble)
        ->and(PlanStageLadder::assemblyModeFor(2))->toBe(ExerciseMode::WordBank)
        ->and(PlanStageLadder::assemblyModeFor(7))->toBe(PlanStageLadder::assemblyModeFor(7));

    expect(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_LINE, 0)[2])
        ->toBe(ExerciseMode::WordBank)
        ->and(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_LINE, 1)[2])
        ->toBe(ExerciseMode::Scramble);
});

/**
 * The rung a plan card is dealt at follows the SAME knob as the card itself.
 *
 * Rung 1 is the identity-graded recognition card: the learner taps, the client uploads a term id,
 * the server compares ids. It is dealt only when the options policy is `distant`. Claiming rung 1
 * while the assembler builds an ordinary choice card — whose answer is the term's TEXT — makes the
 * server grade text against an id and answer `again` to a correct answer. The live S1 run did that
 * to one word of nine: its checklist never closed, and once the pair graduated the re-deal was
 * refused as a stale ladder answer, so the day could not be finished at all.
 */
it('claims the recognition rungs only when the recognition card is actually dealt', function () {
    // `far` → distant options → the identity card exists, so forward then reverse.
    expect(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::MultipleChoice, 1, true))->toBe(1)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::MultipleChoice, 2, true))->toBe(2);

    // `near`/`close` → standard options → ordinary choice cards, graded as text, at the assembly rung.
    expect(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::MultipleChoice, 1, false))->toBe(3)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::MultipleChoice, 2, false))->toBe(3);

    // Everything else is unmoved by the knob.
    expect(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::Intro, 1, false))->toBe(0)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::Speaking, 1, true))->toBe(3)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::B, ExerciseMode::Speaking, 1, true))->toBe(5)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::B, ExerciseMode::Cloze, 1, true))->toBe(3)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::C, ExerciseMode::Typing, 1, false))->toBe(5);
});

it('deals speaking on the FIRST day, and says what is on the screen each time', function () {
    // «Читать вслух рано, говорить без текста поздно.» Stage A deals it to everything; what changes
    // afterwards is what the learner is looking at.
    expect(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_WORD))->toContain(ExerciseMode::Speaking)
        ->and(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_LINE))->toContain(ExerciseMode::Speaking);

    expect(PlanStage::A->speakingForm())->toBe('word_on_screen')
        ->and(PlanStage::B->speakingForm())->toBe('example_with_text')
        ->and(PlanStage::C->speakingForm())->toBe('example_from_memory');

    // A line says ITSELF, so its second speaking card takes the text away rather than moving to a
    // different sentence — and it is graded against the line, not against the turn around it.
    expect(PlanStage::A->speakingForm(PlanStageLadder::KIND_LINE))->toBe('word_on_screen')
        ->and(PlanStage::B->speakingForm(PlanStageLadder::KIND_LINE))->toBe('example_from_memory')
        ->and(PlanStageLadder::ladderStepFor(PlanStage::B, ExerciseMode::Speaking, 1, true, PlanStageLadder::KIND_LINE))
        ->toBe(3);
});

// ── walking a stage ───────────────────────────────────────────────────────────────────────────

it('starts a never-seen word at the intro of stage A', function () {
    $standing = $this->ladder->standingFor(allModes(), [], introduced: false, today: '2026-09-01');

    expect($standing->stage)->toBe(PlanStage::A)
        ->and($standing->nextMode)->toBe(ExerciseMode::Intro)
        ->and($standing->stageComplete)->toBeFalse()
        ->and($standing->checklist)->toHaveCount(4);
});

it('closes the intro step on the EXPOSURE, since an intro writes no review', function () {
    $standing = $this->ladder->standingFor(allModes(), [], introduced: true, today: '2026-09-01');

    expect($standing->nextMode)->toBe(ExerciseMode::MultipleChoice)
        ->and($standing->checklist[0])->toBe(['mode' => 'intro', 'ordinal' => 1, 'done' => true]);
});

it('moves to the next trainer immediately on success, inside the same day', function () {
    $standing = $this->ladder->standingFor(
        allModes(),
        [hit(ExerciseMode::MultipleChoice, '2026-09-01')],
        introduced: true,
        today: '2026-09-01',
    );

    // The SECOND multiple_choice — recognised once is recognised once.
    expect($standing->nextMode)->toBe(ExerciseMode::MultipleChoice)
        ->and($standing->checklist[1]['done'])->toBeTrue()
        ->and($standing->checklist[2]['done'])->toBeFalse();
});

it('does not let a miss close a step', function () {
    $standing = $this->ladder->standingFor(
        allModes(),
        [miss(ExerciseMode::MultipleChoice, '2026-09-01')],
        introduced: true,
        today: '2026-09-01',
    );

    expect($standing->nextMode)->toBe(ExerciseMode::MultipleChoice)
        ->and($standing->checklist[1]['done'])->toBeFalse();
});

// ── the night ─────────────────────────────────────────────────────────────────────────────────

it('holds a word that closed stage A today — the next stage opens after a night', function () {
    $standing = $this->ladder->standingFor(allModes(), stageAFacts('2026-09-01'), true, '2026-09-01');

    expect($standing->stage)->toBe(PlanStage::A)
        ->and($standing->stageComplete)->toBeTrue()
        ->and($standing->waitingForNight)->toBeTrue()
        ->and($standing->nextMode)->toBeNull()
        ->and($standing->finished)->toBeFalse();
});

it('opens stage B on the next local day, at its first trainer', function () {
    $standing = $this->ladder->standingFor(allModes(), stageAFacts('2026-09-01'), true, '2026-09-02');

    expect($standing->stage)->toBe(PlanStage::B)
        ->and($standing->nextMode)->toBe(ExerciseMode::Cloze)
        ->and($standing->waitingForNight)->toBeFalse()
        ->and($standing->checklist)->toHaveCount(2);
});

it('walks all three stages, one night each, and calls the word ready only after C', function () {
    $facts = [
        ...stageAFacts('2026-09-01'),
        hit(ExerciseMode::Cloze, '2026-09-02'),
        hit(ExerciseMode::Typing, '2026-09-02'),
        hit(ExerciseMode::Dictation, '2026-09-03'),
        hit(ExerciseMode::PickCorrect, '2026-09-03'),
    ];

    // The night after stage C closed has not passed yet: closed, but not yet «готово».
    $onTheDay = $this->ladder->standingFor(allModes(), $facts, true, '2026-09-03');
    expect($onTheDay->stage)->toBe(PlanStage::C)
        ->and($onTheDay->stageComplete)->toBeTrue()
        ->and($onTheDay->finished)->toBeTrue()          // C has no next stage to wait for
        ->and($onTheDay->waitingForNight)->toBeFalse();

    $after = $this->ladder->standingFor(allModes(), $facts, true, '2026-09-04');
    expect($after->stage)->toBe(PlanStage::C)
        ->and($after->finished)->toBeTrue()
        ->and($after->isReady())->toBeTrue()
        ->and($after->nextMode)->toBeNull();
});

// ── applicability ─────────────────────────────────────────────────────────────────────────────

it('drops an inapplicable trainer OUT of the checklist instead of blocking on it', function () {
    // No distractors → no pick_correct. Stage C is then three steps, and closing them closes it.
    $applicable = array_values(array_filter(
        allModes(),
        static fn (ExerciseMode $m): bool => $m !== ExerciseMode::PickCorrect,
    ));

    $facts = [
        ...stageAFacts('2026-09-01'),
        hit(ExerciseMode::Cloze, '2026-09-02'),
        hit(ExerciseMode::Typing, '2026-09-02'),
        hit(ExerciseMode::Dictation, '2026-09-03'),
    ];

    $standing = $this->ladder->standingFor($applicable, $facts, true, '2026-09-04');

    expect($standing->stage)->toBe(PlanStage::C)
        ->and($standing->checklist)->toHaveCount(1)
        ->and(array_column($standing->checklist, 'mode'))->not->toContain('pick_correct')
        ->and($standing->finished)->toBeTrue();
});

it('passes straight through a stage whose every trainer is closed off', function () {
    // Nothing of stage B is available at all — no cloze, no typing. The word must not sit there
    // for ever waiting for a card nobody can deal it.
    $applicable = [
        ExerciseMode::Intro, ExerciseMode::MultipleChoice, ExerciseMode::Speaking,
        ExerciseMode::Dictation, ExerciseMode::PickCorrect,
    ];

    // Stage A closes on 09-01; on 09-02 the night has passed, B is empty, so C is what is owed.
    $standing = $this->ladder->standingFor($applicable, stageAFacts('2026-09-01'), true, '2026-09-02');

    expect($standing->stage)->toBe(PlanStage::C)
        ->and($standing->nextMode)->toBe(ExerciseMode::Dictation);
});

it('calls a LINE ready one stage early, because B is the last stage a line has', function () {
    $facts = [
        hit(ExerciseMode::MultipleChoice, '2026-09-01'),
        hit(ExerciseMode::WordBank, '2026-09-01'),
        hit(ExerciseMode::Speaking, '2026-09-01'),
        hit(ExerciseMode::Cloze, '2026-09-02'),
        hit(ExerciseMode::Listening, '2026-09-02'),
        hit(ExerciseMode::Speaking, '2026-09-02'),
    ];

    $standing = $this->ladder->standingFor(
        allModes(), $facts, introduced: true, today: '2026-09-03',
        kind: PlanStageLadder::KIND_LINE, pairCounter: 0,
    );

    expect($standing->stage)->toBe(PlanStage::B)
        ->and($standing->ready)->toBeTrue()
        ->and($standing->finished)->toBeTrue()
        ->and($standing->nextMode)->toBeNull();
});

it('does not call a WORD ready while it is still short of C', function () {
    $standing = $this->ladder->standingFor(allModes(), stageAFacts('2026-09-01'), true, '2026-09-02');

    expect($standing->stage)->toBe(PlanStage::B)
        ->and($standing->ready)->toBeFalse();
});

// ── adaptation ────────────────────────────────────────────────────────────────────────────────

it('softens the knobs after three misses in a row and keeps them soft to the end of the stage', function () {
    $facts = [
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
        hit(ExerciseMode::MultipleChoice, '2026-09-01'),   // a correct answer does NOT undo it
    ];

    $standing = $this->ladder->standingFor(allModes(), $facts, true, '2026-09-01');

    expect($standing->softened)->toBeTrue()
        ->and($standing->stage)->toBe(PlanStage::A);
});

it('does not soften on two misses, nor on three that are not consecutive', function () {
    $two = $this->ladder->standingFor(allModes(), [
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
    ], true, '2026-09-01');

    $spread = $this->ladder->standingFor(allModes(), [
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
        hit(ExerciseMode::MultipleChoice, '2026-09-01'),
        miss(ExerciseMode::Speaking, '2026-09-01'),
        hit(ExerciseMode::MultipleChoice, '2026-09-01'),
        miss(ExerciseMode::Speaking, '2026-09-01'),
    ], true, '2026-09-01');

    expect($two->softened)->toBeFalse()
        ->and($spread->softened)->toBeFalse();
});

it('drops the softening when the stage changes — it was this stage’s concession, not the word’s', function () {
    $facts = [
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
        miss(ExerciseMode::MultipleChoice, '2026-09-01'),
        ...stageAFacts('2026-09-01'),
    ];

    expect($this->ladder->standingFor(allModes(), $facts, true, '2026-09-01')->softened)->toBeTrue()
        ->and($this->ladder->standingFor(allModes(), $facts, true, '2026-09-02')->softened)->toBeFalse();
});

// ── the knobs ─────────────────────────────────────────────────────────────────────────────────

it('ships the наряд’s knob table, level for level', function () {
    expect(PlanKnobs::shipped(PlanLevel::Zero)->toArray())->toBe([
        'mc_options' => 3, 'distractor_closeness' => 'far', 'cloze_blanks' => 1,
        'bank_extra' => 0, 'typing_hint' => 'first_letter', 'tts_rate' => 'slow',
    ])
        ->and(PlanKnobs::shipped(PlanLevel::Basic)->toArray())->toBe([
            'mc_options' => 3, 'distractor_closeness' => 'near', 'cloze_blanks' => 1,
            'bank_extra' => 1, 'typing_hint' => 'none', 'tts_rate' => 'normal',
        ])
        ->and(PlanKnobs::shipped(PlanLevel::Conversational)->toArray())->toBe([
            'mc_options' => 4, 'distractor_closeness' => 'near', 'cloze_blanks' => 2,
            'bank_extra' => 2, 'typing_hint' => 'none', 'tts_rate' => 'normal',
        ])
        ->and(PlanKnobs::shipped(PlanLevel::Fluent)->toArray())->toBe([
            'mc_options' => 4, 'distractor_closeness' => 'close', 'cloze_blanks' => 2,
            'bank_extra' => 2, 'typing_hint' => 'none', 'tts_rate' => 'normal',
        ]);
});

it('steps every knob one notch gentler, and stops at the floor', function () {
    expect(PlanKnobs::shipped(PlanLevel::Fluent)->easier()->toArray())->toBe([
        'mc_options' => 3, 'distractor_closeness' => 'near', 'cloze_blanks' => 1,
        'bank_extra' => 1, 'typing_hint' => 'first_letter', 'tts_rate' => 'slow',
    ]);

    // A `zero` learner who gets stuck is already at the floor everywhere and stays there.
    expect(PlanKnobs::shipped(PlanLevel::Zero)->easier()->toArray())
        ->toBe(PlanKnobs::shipped(PlanLevel::Zero)->toArray());
});

it('translates `far` into the distant options policy the assembler already reads', function () {
    expect(PlanKnobs::shipped(PlanLevel::Zero)->optionsPolicy()->value)->toBe('distant')
        ->and(PlanKnobs::shipped(PlanLevel::Basic)->optionsPolicy()->value)->toBe('standard')
        ->and(PlanKnobs::shipped(PlanLevel::Fluent)->optionsPolicy()->value)->toBe('standard');
});

it('falls back to the level’s shipped value for a half-written stored row', function () {
    $knobs = PlanKnobs::fromArray(PlanLevel::Conversational, ['mc_options' => 3, 'tts_rate' => 'nonsense']);

    expect($knobs->mcOptions)->toBe(3)
        ->and($knobs->ttsRate)->toBe('normal')
        ->and($knobs->clozeBlanks)->toBe(2);
});

/** A WORD's stage A closed on one day: exposure + two recognitions + speaking. */
function stageAFacts(string $date): array
{
    return [
        hit(ExerciseMode::MultipleChoice, $date),
        hit(ExerciseMode::MultipleChoice, $date),
        hit(ExerciseMode::Speaking, $date),
    ];
}
