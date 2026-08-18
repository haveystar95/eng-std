<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\ChipShuffler;
use App\Modules\Learning\Domain\Service\ExerciseSelector;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\Service\PlayabilityAssessor;
use App\Modules\Learning\Domain\Service\SentenceTokenizer;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Learning\Domain\ValueObject\TermPlayability;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

beforeEach(function () {
    $this->selector = new ExerciseSelector();
    $this->phase1 = new EnabledModes([ExerciseMode::MultipleChoice, ExerciseMode::WordBank, ExerciseMode::Typing]);
    $this->all = new EnabledModes([
        ExerciseMode::MultipleChoice, ExerciseMode::WordBank,
        ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Cloze,
    ]);
    $this->matrix = shippedMatrix();
});

/** A term's data-applicability, with the shape most cases want (a multi-word, non-clozeable term). */
function playable(int $answerWordCount = 2, bool $clozeable = false): TermPlayability
{
    return new TermPlayability(answerWordCount: $answerWordCount, clozeable: $clozeable);
}

// shippedMatrix() lives in tests/Pest.php — shared with DictationGateTest, and loaded regardless
// of parallel worker/file order.

/**
 * A pair on the SCHEDULER's dimension. `acquisition` defaults to graduated — the ladder is a
 * separate axis, and every one of these cases is about a term that has finished it.
 */
/**
 * A pair on the SCHEDULING dimension.
 *
 * Two counters, because the selector reads two different things from them: `reps` drives the mode
 * ROTATION (it counts scheduler calls, every grade), while `successes` decides the RUNG and so
 * which modes are admitted at all. They default to moving together, which is what a pair that has
 * never got one wrong looks like; the rows that need them apart pass both.
 */
function atState(LearningState $state, int $reps = 0, ?int $successes = null): TermProgress
{
    return TermProgress::reconstitute(
        UserId::generate(), TermId::generate(), $state, 2.5, 10, null, reps: $reps, lapses: 0, lastReviewedAt: null,
        successfulReviews: $successes ?? $reps,
    );
}

/** A pair on the ACQUISITION dimension, at a given rung of the ladder. */
function onLadder(Acquisition $acquisition, int $learningStep = 0, int $reps = 0, ?int $successes = null): TermProgress
{
    return TermProgress::reconstitute(
        UserId::generate(), TermId::generate(), LearningState::New, 2.5, 0, null,
        reps: $reps, lapses: 0, lastReviewedAt: null,
        acquisition: $acquisition, learningStep: $learningStep,
        successfulReviews: $successes ?? $reps,
    );
}

// ── the acquisition ladder ───────────────────────────────────────────────────

it('shows a never-seen pair the intro when that trainer is on', function () {
    $withIntro = new EnabledModes([ExerciseMode::Intro, ExerciseMode::MultipleChoice, ExerciseMode::Typing]);

    expect($this->selector->select(onLadder(Acquisition::New), $withIntro, playable(), $this->matrix))
        ->toBe(ExerciseMode::Intro);
});

it('starts a never-seen pair at recognition when the intro trainer is off', function () {
    // The release rule ships a new trainer switched OFF, so this is the DEFAULT path today: the
    // ladder still runs, the pair simply never gets rung 0.
    expect($this->selector->select(onLadder(Acquisition::New), $this->phase1, playable(), $this->matrix))
        ->toBe(ExerciseMode::MultipleChoice)
        ->and($this->selector->effectiveStep(onLadder(Acquisition::New), $this->phase1, $this->matrix))
        ->toBe(LearningLadder::STEP_RECOGNITION_FORWARD);
});

it('deals both recognition rungs as multiple_choice, whatever the answer shape', function () {
    // A multi-word term at rung 1 is still recognition: the direction differs, the mode does not.
    foreach ([LearningLadder::STEP_RECOGNITION_FORWARD, LearningLadder::STEP_RECOGNITION_REVERSE] as $step) {
        expect($this->selector->select(onLadder(Acquisition::Learning, $step), $this->all, playable(answerWordCount: 3, clozeable: true), $this->matrix))
            ->toBe(ExerciseMode::MultipleChoice);
    }
});

it('never deals the intro to a pair that has left rung 0', function () {
    $withIntro = new EnabledModes([ExerciseMode::Intro, ExerciseMode::MultipleChoice, ExerciseMode::Typing]);

    expect($this->selector->select(onLadder(Acquisition::Learning, 1), $withIntro, playable(), $this->matrix))
        ->not->toBe(ExerciseMode::Intro)
        ->and($this->selector->select(atState(LearningState::Review, 9), $withIntro, playable(), $this->matrix))
        ->not->toBe(ExerciseMode::Intro);
});

it('holds typed production back until rung 4 and dictation until rung 5', function () {
    // The single word with no example is the case that used to fall through to typing at any rung;
    // now it waits for the reps the ladder asks for and gets ordinary multiple_choice meanwhile.
    $everything = new EnabledModes([
        ExerciseMode::MultipleChoice, ExerciseMode::WordBank, ExerciseMode::Typing,
        ExerciseMode::Listening, ExerciseMode::Cloze, ExerciseMode::Scramble, ExerciseMode::Dictation,
    ]);
    $plain = playable(answerWordCount: 1);

    expect($this->selector->select(atState(LearningState::Review, 0), $everything, $plain, $this->matrix))
        ->toBe(ExerciseMode::MultipleChoice)                                  // rung 3
        ->and($this->selector->select(atState(LearningState::Review, LearningLadder::TYPING_MIN_SUCCESSES), $everything, $plain, $this->matrix))
        ->toBe(ExerciseMode::Typing);                                          // rung 4

    // Dictation needs a sentence as well as the rung, so give it one and check both gates.
    $rich = new TermPlayability(answerWordCount: 3, clozeable: true, exampleTokenCount: 7, hasExampleTranslation: true, distractorCount: 2);
    $modesBelow = [];
    for ($reps = 0; $reps < LearningLadder::DICTATION_MIN_SUCCESSES; $reps++) {
        $modesBelow[] = $this->selector->select(atState(LearningState::Review, $reps), $everything, $rich, $this->matrix);
    }
    expect($modesBelow)->not->toContain(ExerciseMode::Dictation);
});

it('always checks a due known term in typing, ladder or no ladder', function () {
    // `known` is outside the ladder entirely: the matrix would forbid typing at rung 3, and the
    // verification is typing anyway, because recognition proves nothing about a claim.
    expect($this->selector->select(atState(LearningState::Known), $this->phase1, playable(), $this->matrix))
        ->toBe(ExerciseMode::Typing)
        ->and($this->selector->effectiveStep(atState(LearningState::Known), $this->phase1, $this->matrix))
        ->toBeNull();
});

it('rotates review modes deterministically by the review counter (example-backed)', function () {
    // Rung 5 (reps ≥ 6), where the whole historic rotation is admitted, so the phase is unchanged.
    $rev = fn (int $reps) => $this->selector->select(atState(LearningState::Review, $reps), $this->all, playable(clozeable: true), $this->matrix);
    expect($rev(6))->toBe(ExerciseMode::Typing)
        ->and($rev(7))->toBe(ExerciseMode::Listening)
        ->and($rev(8))->toBe(ExerciseMode::Cloze)
        ->and($rev(9))->toBe(ExerciseMode::Typing);
});

it('puts pick_correct LAST in the review rotation, renumbering nothing before it', function () {
    // The ladder order is a contract: a term partway through the rotation must not get its earlier
    // rungs re-dealt because a new rung was switched on. So the newest mode goes on the END and the
    // first five positions must still read the same.
    $everything = new EnabledModes([
        ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Cloze,
        ExerciseMode::Scramble, ExerciseMode::Dictation, ExerciseMode::PickCorrect,
    ]);
    $rich = new TermPlayability(
        answerWordCount: 3,
        clozeable: true,
        exampleTokenCount: 7,          // inside both scramble's and dictation's windows
        hasExampleTranslation: true,
        distractorCount: 2,            // …and pick_correct's gate is met
    );

    // Rung 5, where every mode in the rotation is admitted; the phase is the historic `reps % n`.
    $rev = fn (int $reps) => $this->selector->select(atState(LearningState::Review, $reps), $everything, $rich, $this->matrix);

    expect($rev(6))->toBe(ExerciseMode::Typing)
        ->and($rev(7))->toBe(ExerciseMode::Listening)
        ->and($rev(8))->toBe(ExerciseMode::Cloze)
        ->and($rev(9))->toBe(ExerciseMode::Scramble)
        ->and($rev(10))->toBe(ExerciseMode::Dictation)
        ->and($rev(11))->toBe(ExerciseMode::PickCorrect)
        ->and($rev(12))->toBe(ExerciseMode::Typing);   // wraps
});

it('leads the production rotation with the base mode once typing is admitted, then fans out', function () {
    // Rung 4 (reps ≥ 4): typing and listening join, dictation does not.
    $single = fn (int $reps) => $this->selector->select(atState(LearningState::Learning, $reps), $this->all, playable(answerWordCount: 1, clozeable: true), $this->matrix);
    // The offset is (reps - 1), so the FIRST meeting at which typing is admitted (reps 4) lands on
    // the base mode and later ones fan out — the same "base first" phase the rung 3 rotation had.
    expect($single(4))->toBe(ExerciseMode::Typing)
        ->and($single(5))->toBe(ExerciseMode::Listening)
        ->and($single(6))->toBe(ExerciseMode::Cloze)
        ->and($single(7))->toBe(ExerciseMode::Typing);

    // Multi-word: base is word_bank; the rotation leads with it.
    $multi = fn (int $reps) => $this->selector->select(atState(LearningState::Learning, $reps), $this->all, playable(answerWordCount: 3, clozeable: true), $this->matrix);
    expect($multi(4))->toBe(ExerciseMode::WordBank)
        ->and($multi(5))->toBe(ExerciseMode::Listening)
        ->and($multi(6))->toBe(ExerciseMode::Cloze);
});

it('never offers cloze when the term has no usable example (falls through to the typed ladder)', function () {
    // clozeable defaults false → cloze is dropped from every rotation.
    $single = fn (int $reps) => $this->selector->select(atState(LearningState::Learning, $reps), $this->all, playable(answerWordCount: 1), $this->matrix);
    expect($single(5))->toBe(ExerciseMode::Typing)
        ->and($single(6))->toBe(ExerciseMode::Listening)
        ->and($single(7))->toBe(ExerciseMode::Typing); // wraps typing/listening — cloze never appears

    // Review with no example: typing/listening only.
    $rev = fn (int $reps) => $this->selector->select(atState(LearningState::Review, $reps), $this->all, playable(), $this->matrix);
    expect($rev(6))->toBe(ExerciseMode::Typing)
        ->and($rev(7))->toBe(ExerciseMode::Listening)
        ->and($rev(8))->toBe(ExerciseMode::Typing);
});

it('degrades review to the only enabled review mode in phase 1', function () {
    // Among typing/listening/cloze, only typing is on — every review card is typing (rung 4+).
    expect($this->selector->select(atState(LearningState::Review, 9), $this->phase1, playable(), $this->matrix))->toBe(ExerciseMode::Typing);
});

it('falls back to an enabled mode when the preferred one is switched off', function () {
    $onlyMc = new EnabledModes([ExerciseMode::MultipleChoice]);

    // A produced single word prefers typing, which is off → fall back to the one enabled mode.
    expect($this->selector->select(atState(LearningState::Learning, 5), $onlyMc, playable(answerWordCount: 1), $this->matrix))
        ->toBe(ExerciseMode::MultipleChoice);
});

// ── the admission matrix, as a table ─────────────────────────────────────────
//
// The matrix is product policy that WILL move, so the table asserts the shipped config rather
// than restating it: what is pinned here is the shape (which rungs admit which trainer), and a
// config change that contradicts the ladder's intent shows up as a red row.

dataset('admission', [
    //  mode,                        admitted at rungs
    'intro is rung 0 and nowhere else' => [ExerciseMode::Intro, [0]],
    'multiple_choice from rung 1'      => [ExerciseMode::MultipleChoice, [1, 2, 3, 4, 5]],
    'word_bank from rung 3'            => [ExerciseMode::WordBank, [3, 4, 5]],
    'cloze from rung 3'                => [ExerciseMode::Cloze, [3, 4, 5]],
    'scramble from rung 3'             => [ExerciseMode::Scramble, [3, 4, 5]],
    'pick_correct from rung 3'         => [ExerciseMode::PickCorrect, [3, 4, 5]],
    'typing from rung 4'               => [ExerciseMode::Typing, [4, 5]],
    'listening from rung 4'            => [ExerciseMode::Listening, [4, 5]],
    'dictation only at rung 5'         => [ExerciseMode::Dictation, [5]],
]);

it('admits each trainer exactly at its rungs', function (ExerciseMode $mode, array $rungs) {
    $matrix = shippedMatrix();

    foreach (range(0, 5) as $rung) {
        expect($matrix->allows($mode, $rung))->toBe(
            in_array($rung, $rungs, true),
            "{$mode->value} at rung {$rung}",
        );
    }
})->with('admission');

it('admits nothing for a mode the matrix does not mention', function () {
    // Fail-closed: a trainer someone forgot to place on the ladder is undealable, not universal.
    $partial = new ModeAdmission(['typing' => ['min' => 0]]);

    foreach (range(0, 5) as $rung) {
        expect($partial->allows(ExerciseMode::Dictation, $rung))->toBeFalse();
    }
});

// ── the ladder function, as a table ──────────────────────────────────────────

dataset('ladder', [
    'never shown → intro'                    => [Acquisition::New, 0, 0, false, 0],
    'never shown, reps survived a known undo' => [Acquisition::New, 9, 0, false, 0],
    'introduced → recognition forward'        => [Acquisition::Learning, 0, 1, false, 1],
    'forward passed → recognition reverse'    => [Acquisition::Learning, 0, 2, false, 2],
    'graduated, no SRS review yet'            => [Acquisition::Graduated, 0, 0, false, 3],
    'graduated, three reviews in'             => [Acquisition::Graduated, 3, 0, false, 3],
    'graduated, typing unlocked'              => [Acquisition::Graduated, 4, 0, false, 4],
    'graduated, still rung 4'                 => [Acquisition::Graduated, 5, 0, false, 4],
    'graduated, dictation unlocked'           => [Acquisition::Graduated, 6, 0, false, 5],
    'a long-established pair stays at 5'      => [Acquisition::Graduated, 40, 0, false, 5],
    'known is outside the ladder'             => [Acquisition::Graduated, 0, 0, true, null],
    'a known pair mid-ladder is still out'    => [Acquisition::Learning, 0, 1, true, null],
    'a step from a newer build is clamped'    => [Acquisition::Learning, 0, 7, false, 2],
]);

it('derives the rung from (acquisition, reps, learning_step)', function (
    Acquisition $acquisition,
    int $reps,
    int $learningStep,
    bool $isKnown,
    ?int $expected,
) {
    expect(LearningLadder::stepFor($acquisition, $reps, $learningStep, $isKnown))->toBe($expected);
})->with('ladder');

// The clamp, on its own. `stepFor` applies it, but so does the session assembler, which needs a
// non-nullable answer — one function, so a future widening of the ladder cannot move one and
// not the other. Out-of-range values only arrive from a newer build's row (the column is CHECKed
// to 0…2), and the answer must be a rung, not null and not a throw.
dataset('clamp', [
    'the stored default, before the intro has happened' => [0, 1],
    'forward'                                           => [1, 1],
    'reverse'                                           => [2, 2],
    'a rung a newer build added'                        => [3, 2],
    'the step the client test pins'                     => [7, 2],
    'nonsense from a hand-edit'                         => [-4, 1],
]);

it('clamps a stored step into the recognition rungs', function (int $stored, int $expected) {
    expect(LearningLadder::clampRecognitionStep($stored))->toBe($expected);
})->with('clamp');

// ── free practice: fan across ALL applicable modes (not the reps ladder) ─────────

/** @return list<ExerciseMode> */
function practiceRun(ExerciseSelector $sel, EnabledModes $enabled, int $wordCount, bool $clozeable, int $rounds = 10): array
{
    // rotation is card-index + a per-term offset in production; here we sweep indices to see the fan.
    $out = [];
    for ($i = 0; $i < $rounds; $i++) {
        $out[] = $sel->selectForPractice($enabled, $i, playable(answerWordCount: $wordCount, clozeable: $clozeable));
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
    $a = $this->selector->selectForPractice($this->all, 0, playable(answerWordCount: 3, clozeable: true));
    $b = $this->selector->selectForPractice($this->all, 0, playable(answerWordCount: 3, clozeable: true));
    expect($a)->toBe($b);

    // A negative seed (a large per-term offset can push it) still maps into range.
    expect($this->selector->selectForPractice($this->all, -1, playable(answerWordCount: 3, clozeable: true)))->toBeInstanceOf(ExerciseMode::class);
});

it('practice honours the enabled set — a phase-1 config never yields listening or cloze', function () {
    $modes = practiceRun($this->selector, $this->phase1, wordCount: 3, clozeable: true);
    $seen = array_unique(array_map(fn (ExerciseMode $m): string => $m->value, $modes));

    expect($seen)->toEqualCanonicalizing(['multiple_choice', 'word_bank', 'typing']);
});

// ── cross-runtime contract fixture ───────────────────────────────────────────
//
// Free practice is built on the DEVICE (offline-first), so the mode ladder now has two
// implementations — this one and the Dart port. Two implementations of one rule drift; the only
// thing that stops it is a fixture generated BY this code and asserted by both.
//
// The fixture is committed. This test regenerates it on demand and otherwise fails if the current
// selector no longer produces it, so a server-side change to the ladder cannot land quietly:
//
//     docker compose exec app php artisan test --filter=practice_contract          # verify
//     docker compose exec -e EXPORT_PRACTICE_FIXTURE=1 app php artisan test --filter=practice_contract
//
// The Dart side reads the same file (mobile/test/data/practice/…_contract_test.dart).

/**
 * The cases the fixture pins. Fixed term ids and answers — the rotation seed is
 * `cardIndex + crc32(termId)`, so the ids must never change or the expectations move with them.
 *
 * @return list<array{term_id: string, card_index: int, answer: string, example: string|null, example_translation: string|null}>
 */
function practiceContractCases(): array
{
    $termIds = [
        '01KZETAAA50EMHCN6SP80T8DHC',
        '01KZETAAB4AW6M9ZFRB3X02CVW',
        '01KZETAAC103WZ24WQ7H087ZJ3',
        '01KZETAAD2EWE2H5ZV7WD8JWKT',
        '01KZETAAE63W6K93C55NCYXKVA',
        '01KZETAAF7QK4M2NB9XR6TC1YZ',
        '01KZETAAG3WD5P7HJ2VT8NQ4XB',
        '01KZETAAH6ZC2Q9MK4RB7XD3VT',
        '01KZETAAJ4XN8V2CQ7MB5RT9WD',
    ];
    // Deliberately covers every applicability branch: single word vs phrase (word_bank); example
    // containing the answer vs not vs absent (cloze); a mixed-case example (the match is
    // case-insensitive on both sides); an untranslated example and one that is merely the term
    // itself; and sentences on both sides of BOTH windows — scramble's 4…12 and dictation's
    // tighter 4…10, which only a sentence of 11 or 12 words tells apart.
    $answers = [
        ['answer' => 'reservation', 'example' => 'I have a reservation for tonight.', 'example_translation' => 'У меня бронь на сегодня.'],
        ['answer' => 'give up', 'example' => "I won't give up until I've achieved my goals.", 'example_translation' => 'Я не сдамся, пока не добьюсь своего.'],
        ['answer' => 'front desk', 'example' => null, 'example_translation' => null],
        ['answer' => 'towel', 'example' => 'Could I have extra sheets, please?', 'example_translation' => 'Можно мне ещё простыни?'],
        // Example present but never translated → no question to ask, so no scramble.
        ['answer' => 'check in', 'example' => 'CHECK IN starts at 3 pm.', 'example_translation' => null],
        // The "example" IS the term — scrambling it would deal word_bank's tiles a second time.
        ['answer' => 'Nice to meet you', 'example' => 'Nice to meet you.', 'example_translation' => 'Приятно познакомиться.'],
        // Three chips: below the floor.
        ['answer' => 'hurry', 'example' => 'Please hurry up.', 'example_translation' => 'Пожалуйста, поторопись.'],
        // Sixteen chips: above the ceiling.
        ['answer' => 'suitcase', 'example' => 'I left my suitcase at the hotel and had to go back for it in the evening.', 'example_translation' => 'Я оставил чемодан в отеле и вечером вернулся за ним.'],
        // Eleven chips: inside scramble's window, past dictation's. The one case that proves the
        // two ceilings are actually different numbers on both runtimes.
        ['answer' => 'umbrella', 'example' => 'She left her umbrella on the train and never saw it again.', 'example_translation' => 'Она забыла зонт в поезде и больше его не видела.'],
    ];

    $cases = [];
    foreach ($termIds as $t => $termId) {
        foreach ([0, 1, 2, 3, 7] as $cardIndex) {
            $cases[] = [
                'term_id' => $termId,
                'card_index' => $cardIndex,
                'answer' => $answers[$t]['answer'],
                'example' => $answers[$t]['example'],
                'example_translation' => $answers[$t]['example_translation'],
            ];
        }
    }

    return $cases;
}

/** The seed StudyCardAssembler feeds the selector for a practice card. Kept in one place. */
function practiceRotation(string $termId, int $cardIndex): int
{
    return $cardIndex + (int) crc32($termId);
}

it('practice_contract: the committed fixture still matches this selector', function () {
    $assessor = new PlayabilityAssessor(new ChipShuffler(), new SentenceTokenizer());
    // The SHIPPED default, which is what a device assumes before its first sync — deliberately not
    // "every mode the enum has". A trainer released switched off (dictation) is absent here on
    // purpose; what pins ITS behaviour across the two runtimes is `supported_modes` below, which
    // is about the term's data and so is independent of anyone's toggles.
    $modes = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble'];
    $enabled = new EnabledModes(array_map(
        static fn (string $m): ExerciseMode => ExerciseMode::from($m),
        $modes,
    ));

    $cases = [];
    foreach (practiceContractCases() as $case) {
        $answer = $case['answer'];
        // Exactly the derivation StudyCardAssembler does, so the port is pinned on these too —
        // every gate input is one more place the two runtimes can drift apart.
        $playable = $assessor->assess($answer, $case['example'], $case['example_translation']);

        $cases[] = [
            ...$case,
            'word_count' => $playable->answerWordCount,
            'clozeable' => $playable->clozeable,
            'example_token_count' => $playable->exampleTokenCount,
            'has_example_translation' => $playable->hasExampleTranslation,
            'example_is_answer' => $playable->exampleIsAnswer,
            // Every mode this term's DATA supports, whatever is switched on. This is what pins a
            // gate across the two runtimes for a mode nobody has enabled yet — by the time someone
            // does, the client either already agreed or the fixture went red.
            // Graded modes only. `intro` is applicable to every term by construction (it asks for
            // nothing, so no content can be missing) and is never dealt in practice, so listing it
            // here would add a constant to the fixture rather than a fact about the term.
            'supported_modes' => array_values(array_map(
                static fn (ExerciseMode $m): string => $m->value,
                array_filter(
                    ExerciseMode::cases(),
                    static fn (ExerciseMode $m): bool => $m->isGraded() && $playable->supports($m),
                ),
            )),
            'rotation' => practiceRotation($case['term_id'], $case['card_index']),
            'expected_mode' => $this->selector->selectForPractice(
                $enabled,
                practiceRotation($case['term_id'], $case['card_index']),
                $playable,
            )->value,
        ];
    }

    $fixture = ['enabled_modes' => $modes, 'cases' => $cases];
    // Unit tests here don't boot the framework, so no base_path(): tests/Unit/Learning → tests/.
    $path = dirname(__DIR__, 2).'/Fixtures/practice-mode-contract.json';
    $json = json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

    if (getenv('EXPORT_PRACTICE_FIXTURE') === '1') {
        file_put_contents($path, $json);
    }

    expect(file_exists($path))->toBeTrue('fixture missing — regenerate with EXPORT_PRACTICE_FIXTURE=1');
    expect(file_get_contents($path))->toBe(
        $json,
        'the practice ladder changed. Regenerate with EXPORT_PRACTICE_FIXTURE=1 and re-run the Dart contract test.',
    );
});
