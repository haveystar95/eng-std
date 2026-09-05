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
 *
 * SINCE DAY-FIX-2 (Ч.2) the ladder is ONE TOUCH PER STAGE, and no stage of any kind deals a typed
 * trainer: a day is one sitting of at most forty cards, and the keyboard is not a plan exercise.
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

it('deals a WORD its ladder: meet it, then recognise it, then say it — one touch a stage', function () {
    expect(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_WORD))->toBe([ExerciseMode::Intro])
        ->and(PlanStageLadder::modesOf(PlanStage::B, PlanStageLadder::KIND_WORD))->toBe([ExerciseMode::MultipleChoice])
        ->and(PlanStageLadder::modesOf(PlanStage::C, PlanStageLadder::KIND_WORD))->toBe([ExerciseMode::Speaking]);
});

it('deals a LINE (the rescue kit) its own ladder — met, recognised, and no stage C at all', function () {
    expect(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_LINE))->toBe([ExerciseMode::Intro])
        ->and(PlanStageLadder::modesOf(PlanStage::B, PlanStageLadder::KIND_LINE))->toBe([ExerciseMode::MultipleChoice])
        ->and(PlanStageLadder::modesOf(PlanStage::C, PlanStageLadder::KIND_LINE))->toBe([])
        ->and(PlanStageLadder::lastStageFor(PlanStageLadder::KIND_LINE))->toBe(PlanStage::B)
        ->and(PlanStageLadder::nextStageFor(PlanStage::B, PlanStageLadder::KIND_LINE))->toBeNull();
});

it('gives a CONNECTOR the word`s ladder exactly — the difference is where its gap is cut', function () {
    foreach (PlanStage::cases() as $stage) {
        expect(PlanStageLadder::modesOf($stage, PlanStageLadder::KIND_CHUNK))
            ->toBe(PlanStageLadder::modesOf($stage, PlanStageLadder::KIND_WORD));
    }
});

it('never names a TYPED trainer anywhere — the keyboard is not a plan exercise (DAY-FIX-2, Ч.2.6)', function () {
    foreach ([PlanStageLadder::KIND_WORD, PlanStageLadder::KIND_CHUNK, PlanStageLadder::KIND_LINE,
        PlanStageLadder::KIND_UNDERSTAND, PlanStageLadder::KIND_LINE_SAY, PlanStageLadder::KIND_LINE_ASK] as $kind) {
        foreach (PlanStage::cases() as $stage) {
            foreach (PlanStageLadder::modesOf($stage, $kind) as $mode) {
                // `intro` accepts no answer at all, so it has no typos to forgive — and says so.
                expect($mode === ExerciseMode::Intro || ! $mode->forgivesTypos())
                    ->toBeTrue("{$kind} at {$stage->value} deals {$mode->value}");
            }
        }
        foreach (PlanStageLadder::modesEverDealtTo($kind) as $mode) {
            expect($mode === ExerciseMode::Intro || ! $mode->forgivesTypos())
                ->toBeTrue("{$kind} may ever be dealt {$mode->value}");
        }
    }

    // …and the rescue kit's maintenance touches are assembly and the voice, never dictation.
    foreach ([0, 1, 2, 3] as $slot) {
        expect(PlanStageLadder::maintenanceModeFor($slot)->forgivesTypos())->toBeFalse();
    }
    expect(PlanStageLadder::maintenanceModeFor(0))->not->toBe(PlanStageLadder::maintenanceModeFor(1));
});

it('alternates the assembly alternative by the PAIR, never by chance', function () {
    expect(PlanStageLadder::assemblyModeFor(0))->toBe(ExerciseMode::WordBank)
        ->and(PlanStageLadder::assemblyModeFor(1))->toBe(ExerciseMode::Scramble)
        ->and(PlanStageLadder::assemblyModeFor(2))->toBe(ExerciseMode::WordBank)
        ->and(PlanStageLadder::assemblyModeFor(7))->toBe(PlanStageLadder::assemblyModeFor(7));
});

it('falls the assembly alternative BACK to the word bank rather than dropping it (С-7)', function () {
    $onlyWordBank = [ExerciseMode::Intro, ExerciseMode::MultipleChoice, ExerciseMode::WordBank, ExerciseMode::Speaking];

    expect(PlanStageLadder::assemblyModeFor(1, $onlyWordBank))->toBe(ExerciseMode::WordBank)
        ->and(PlanStageLadder::assemblyModeFor(1, [...$onlyWordBank, ExerciseMode::Scramble]))
        ->toBe(ExerciseMode::Scramble)
        ->and(PlanStageLadder::assemblyModeFor(1))->toBe(ExerciseMode::Scramble);
});

/**
 * The rung a plan card is dealt at follows the SAME knob as the card itself.
 */
it('claims the recognition rungs only when the recognition card is actually dealt', function () {
    expect(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::MultipleChoice, 1, true))->toBe(1)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::MultipleChoice, 2, true))->toBe(2);

    expect(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::MultipleChoice, 1, false))->toBe(3)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::MultipleChoice, 2, false))->toBe(3);

    expect(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::Intro, 1, false))->toBe(0)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::A, ExerciseMode::Speaking, 1, true))->toBe(3)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::B, ExerciseMode::Speaking, 1, true))->toBe(5)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::B, ExerciseMode::MultipleChoice, 1, true))->toBe(3)
        // Stage C of a WORD is «сказал сам»: the word from memory, out loud — the assembly rung,
        // which asks for the word, not the dictation rung, which would ask for the example.
        ->and(PlanStageLadder::ladderStepFor(PlanStage::C, ExerciseMode::Speaking, 1, false))->toBe(3)
        ->and(PlanStageLadder::ladderStepFor(PlanStage::C, ExerciseMode::PickCorrect, 1, false))->toBe(5);
});

it('says what the speaking card shows at each stage', function () {
    expect(PlanStage::A->speakingForm())->toBe('word_on_screen')
        ->and(PlanStage::B->speakingForm())->toBe('example_with_text')
        ->and(PlanStage::C->speakingForm())->toBe('example_from_memory');

    expect(PlanStage::A->speakingForm(PlanStageLadder::KIND_LINE))->toBe('word_on_screen')
        ->and(PlanStage::B->speakingForm(PlanStageLadder::KIND_LINE))->toBe('example_from_memory')
        ->and(PlanStageLadder::ladderStepFor(PlanStage::B, ExerciseMode::Speaking, 1, true, PlanStageLadder::KIND_LINE))
        ->toBe(3);
});

// ── walking a stage ───────────────────────────────────────────────────────────────────────────

it('starts a never-seen word at the intro of stage A — and that is the whole of stage A', function () {
    $standing = $this->ladder->standingFor(allModes(), [], introducedOn: null, today: '2026-09-01');

    expect($standing->stage)->toBe(PlanStage::A)
        ->and($standing->nextMode)->toBe(ExerciseMode::Intro)
        ->and($standing->stageComplete)->toBeFalse()
        ->and($standing->checklist)->toHaveCount(1);
});

it('closes stage A of a word on the EXPOSURE, and holds it until the night', function () {
    $standing = $this->ladder->standingFor(allModes(), [], introducedOn: '2026-09-01', today: '2026-09-01');

    expect($standing->stage)->toBe(PlanStage::A)
        ->and($standing->stageComplete)->toBeTrue()
        ->and($standing->waitingForNight)->toBeTrue()
        ->and($standing->nextMode)->toBeNull()
        ->and($standing->checklist[0])->toBe(['mode' => 'intro', 'ordinal' => 1, 'done' => true]);
});

it('does not let a miss close a step', function () {
    $standing = $this->ladder->standingFor(
        allModes(),
        [miss(ExerciseMode::MultipleChoice, '2026-09-02')],
        introducedOn: '2026-09-01',
        today: '2026-09-02',
    );

    expect($standing->stage)->toBe(PlanStage::B)
        ->and($standing->nextMode)->toBe(ExerciseMode::MultipleChoice)
        ->and($standing->checklist[0]['done'])->toBeFalse();
});

// ── the night ─────────────────────────────────────────────────────────────────────────────────

it('opens stage B of a word on the next local day, at recognition — the night is measured from the exposure', function () {
    // The exposure is the ONLY thing that closes stage A now, so its date is what the night is
    // measured from: shown on the 1st, recognised from the 2nd.
    $standing = $this->ladder->standingFor(allModes(), [], introducedOn: '2026-09-01', today: '2026-09-02');

    expect($standing->stage)->toBe(PlanStage::B)
        ->and($standing->nextMode)->toBe(ExerciseMode::MultipleChoice)
        ->and($standing->waitingForNight)->toBeFalse()
        ->and($standing->checklist)->toHaveCount(1);
});

it('walks a word through B and C, one night each, and calls it ready only after C', function () {
    $facts = [
        hit(ExerciseMode::MultipleChoice, '2026-09-02'),
        hit(ExerciseMode::Speaking, '2026-09-03'),
    ];

    // ON THE DAY IT WAS MET a word stands on A, closed by the exposure, waiting for its night: the
    // choice logged the same day belongs to tomorrow's B (one touch per stage, DAY-FIX-2).
    $b = $this->ladder->standingFor(allModes(), [hit(ExerciseMode::MultipleChoice, '2026-09-02')], '2026-09-02', '2026-09-02');
    expect($b->stage)->toBe(PlanStage::A)
        ->and($b->stageComplete)->toBeTrue()
        ->and($b->waitingForNight)->toBeTrue()
        ->and($b->nextMode)->toBeNull();

    $c = $this->ladder->standingFor(allModes(), [hit(ExerciseMode::MultipleChoice, '2026-09-02')], '2026-09-02', '2026-09-03');
    expect($c->stage)->toBe(PlanStage::C)
        ->and($c->nextMode)->toBe(ExerciseMode::Speaking);

    $onTheDay = $this->ladder->standingFor(allModes(), $facts, '2026-09-02', '2026-09-03');
    expect($onTheDay->stage)->toBe(PlanStage::C)
        ->and($onTheDay->stageComplete)->toBeTrue()
        ->and($onTheDay->finished)->toBeTrue()
        ->and($onTheDay->isReady())->toBeTrue()
        ->and($onTheDay->nextMode)->toBeNull();
});

// ── the same-day dialogue (решение владельца 05.09) ──────────────────────────────────────────

it('opens stage B of a scene line the SAME day its A closed — met, then spoken, in one sitting', function () {
    foreach ([PlanStageLadder::KIND_LINE_SAY, PlanStageLadder::KIND_LINE_ASK, PlanStageLadder::KIND_UNDERSTAND] as $kind) {
        $standing = $this->ladder->standingFor(allModes(), [], introducedOn: '2026-09-01', today: '2026-09-01', kind: $kind);

        expect($standing->stage)->toBe(PlanStage::B, $kind)
            ->and($standing->nextMode)->toBe(match ($kind) {
                PlanStageLadder::KIND_LINE_SAY => ExerciseMode::SituationalSay,
                PlanStageLadder::KIND_LINE_ASK => ExerciseMode::SituationalAsk,
                default => ExerciseMode::SituationalHear,
            });
        expect(PlanStageLadder::opensBSameDay($kind))->toBeTrue()
            ->and(PlanStageLadder::oneShowPerDay($kind))->toBeTrue();
    }

    // …and NOT for a word or the rescue kit: recognising a word the evening it was shown is a test
    // of twenty minutes' memory.
    foreach ([PlanStageLadder::KIND_WORD, PlanStageLadder::KIND_CHUNK, PlanStageLadder::KIND_LINE] as $kind) {
        expect(PlanStageLadder::opensBSameDay($kind))->toBeFalse($kind)
            ->and($this->ladder->standingFor(allModes(), [], introducedOn: '2026-09-01', today: '2026-09-01', kind: $kind)->stage)
            ->toBe(PlanStage::A);
    }
});

it('gives «Ты ответишь» and «Ты спросишь» their stage B as the situational card, twice', function () {
    // B and B+ on one trainer: the choice, and then the assembly on the line's NEXT appearance
    // (наряд SCENE-RUN, Ч.1). One step would make the assembly unreachable rather than rare.
    expect(PlanStageLadder::modesOf(PlanStage::B, PlanStageLadder::KIND_LINE_SAY))
        ->toBe([ExerciseMode::SituationalSay, ExerciseMode::SituationalSay])
        ->and(PlanStageLadder::modesOf(PlanStage::B, PlanStageLadder::KIND_LINE_ASK))
        ->toBe([ExerciseMode::SituationalAsk, ExerciseMode::SituationalAsk])
        ->and(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_LINE_SAY))->toBe([ExerciseMode::Intro])
        ->and(PlanStageLadder::modesOf(PlanStage::C, PlanStageLadder::KIND_LINE_SAY))->toBe([])
        ->and(PlanStageLadder::modesOf(PlanStage::C, PlanStageLadder::KIND_LINE_ASK))->toBe([]);
});

it('makes the second touch of «Тебе скажут» the situational card, not a dictation of it', function () {
    expect(PlanStageLadder::modesOf(PlanStage::A, PlanStageLadder::KIND_UNDERSTAND))->toBe([ExerciseMode::Intro])
        ->and(PlanStageLadder::modesOf(PlanStage::B, PlanStageLadder::KIND_UNDERSTAND))->toBe([ExerciseMode::SituationalHear])
        ->and(PlanStageLadder::modesOf(PlanStage::C, PlanStageLadder::KIND_UNDERSTAND))->toBe([]);
});

it('closes a reply’s first B step on the situational card and leaves the second for the next show', function () {
    $facts = [hit(ExerciseMode::SituationalSay, '2026-09-01')];

    $standing = $this->ladder->standingFor(
        allModes(), $facts, introducedOn: '2026-09-01', today: '2026-09-01',
        kind: PlanStageLadder::KIND_LINE_SAY,
    );

    expect($standing->stage)->toBe(PlanStage::B)
        ->and($standing->checklist[0]['done'])->toBeTrue()
        ->and($standing->checklist[1]['done'])->toBeFalse()
        ->and($standing->nextMode)->toBe(ExerciseMode::SituationalSay);

    // Both closed → the line is ready (B is its last stage), and finished after the night.
    $both = [...$facts, hit(ExerciseMode::SituationalSay, '2026-09-02')];
    $after = $this->ladder->standingFor(allModes(), $both, introducedOn: '2026-09-01', today: '2026-09-03', kind: PlanStageLadder::KIND_LINE_SAY);
    expect($after->ready)->toBeTrue()
        ->and($after->finished)->toBeTrue();
});

// ── applicability ─────────────────────────────────────────────────────────────────────────────

it('drops an inapplicable trainer OUT of the checklist instead of blocking on it', function () {
    // No speaking → stage C is empty and passed straight through: the word is finished after B.
    $applicable = [ExerciseMode::Intro, ExerciseMode::MultipleChoice];

    $standing = $this->ladder->standingFor($applicable, [hit(ExerciseMode::MultipleChoice, '2026-09-02')], '2026-09-02', '2026-09-03');

    expect($standing->stage)->toBe(PlanStage::C)
        ->and($standing->checklist)->toBe([])
        ->and($standing->finished)->toBeTrue();
});

it('reads the shelf to tell a reply from a question, and only for a spoken line', function () {
    expect(PlanStageLadder::ladderKindFor('line', 'speak', 'say'))->toBe(PlanStageLadder::KIND_LINE_SAY)
        ->and(PlanStageLadder::ladderKindFor('line', 'speak', 'ask'))->toBe(PlanStageLadder::KIND_LINE_ASK)
        ->and(PlanStageLadder::ladderKindFor('line', 'speak', 'rescue'))->toBe(PlanStageLadder::KIND_LINE)
        ->and(PlanStageLadder::ladderKindFor('line', null, null))->toBe(PlanStageLadder::KIND_LINE)
        ->and(PlanStageLadder::ladderKindFor('line', 'understand', 'hear'))->toBe(PlanStageLadder::KIND_UNDERSTAND)
        ->and(PlanStageLadder::ladderKindFor('word', 'speak', 'words'))->toBe(PlanStageLadder::KIND_WORD);
});

// ── adaptation ────────────────────────────────────────────────────────────────────────────────

it('softens the knobs after three misses in a row and keeps them soft to the end of the stage', function () {
    $facts = [
        miss(ExerciseMode::MultipleChoice, '2026-09-02'),
        miss(ExerciseMode::MultipleChoice, '2026-09-02'),
        miss(ExerciseMode::MultipleChoice, '2026-09-02'),
        hit(ExerciseMode::MultipleChoice, '2026-09-02'),   // a correct answer does NOT undo it
    ];

    // A word on stage B (introduced, night passed — measured by the caller): three misses on the
    // recognition card soften the stage.
    $standing = $this->ladder->standingFor(
        allModes(), $facts, introducedOn: '2026-09-01', today: '2026-09-02', kind: PlanStageLadder::KIND_LINE_SAY,
    );

    expect($standing->stage)->toBe(PlanStage::B)
        ->and($standing->softened)->toBeTrue();
});

it('does not soften on two misses, nor on three that are not consecutive', function () {
    $two = $this->ladder->standingFor(allModes(), [
        miss(ExerciseMode::SituationalSay, '2026-09-01'),
        miss(ExerciseMode::SituationalSay, '2026-09-01'),
    ], '2026-09-01', '2026-09-01', PlanStageLadder::KIND_LINE_SAY);

    $spread = $this->ladder->standingFor(allModes(), [
        miss(ExerciseMode::SituationalSay, '2026-09-01'),
        hit(ExerciseMode::SituationalSay, '2026-09-01'),
        miss(ExerciseMode::SituationalSay, '2026-09-01'),
    ], '2026-09-01', '2026-09-01', PlanStageLadder::KIND_LINE_SAY);

    $three = $this->ladder->standingFor(allModes(), [
        miss(ExerciseMode::SituationalSay, '2026-09-01'),
        miss(ExerciseMode::SituationalSay, '2026-09-01'),
        miss(ExerciseMode::SituationalSay, '2026-09-01'),
    ], '2026-09-01', '2026-09-01', PlanStageLadder::KIND_LINE_SAY);

    expect($two->softened)->toBeFalse()
        ->and($spread->softened)->toBeFalse()
        ->and($three->softened)->toBeTrue();
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

// ── разогрев v2: «непослушная» карточка ──────────────────────────────────────────────────────

it('remembers that a card was missed YESTERDAY, and not that it was missed today', function () {
    $facts = [
        miss(ExerciseMode::MultipleChoice, '2026-09-02'),
        hit(ExerciseMode::MultipleChoice, '2026-09-03'),
    ];

    $yesterdays = $this->ladder->standingFor(
        allModes(), $facts, introducedOn: '2026-09-01', today: '2026-09-03', yesterday: '2026-09-02',
    );
    $todays = $this->ladder->standingFor(
        allModes(), $facts, introducedOn: '2026-09-01', today: '2026-09-02', yesterday: '2026-09-01',
    );

    expect($yesterdays->missedYesterday)->toBeTrue()
        ->and($todays->missedYesterday)->toBeFalse();
});

it('counts a miss that was corrected later the same day — the hand did not know it', function () {
    $facts = [
        miss(ExerciseMode::MultipleChoice, '2026-09-02'),
        hit(ExerciseMode::MultipleChoice, '2026-09-02'),
    ];

    expect($this->ladder->standingFor(
        allModes(), $facts, introducedOn: '2026-09-01', today: '2026-09-03', yesterday: '2026-09-02',
    )->missedYesterday)->toBeTrue();
});

it('says nothing about yesterday when nobody told it which day that was', function () {
    $facts = [miss(ExerciseMode::MultipleChoice, '2026-09-02')];

    expect($this->ladder->standingFor(allModes(), $facts, introducedOn: '2026-09-01', today: '2026-09-03')
        ->missedYesterday)->toBeFalse();
});
