<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\ChipShuffler;
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
 * @return list<array{term_id: string, card_index: int, answer: string, example: string|null}>
 */
function practiceContractCases(): array
{
    $termIds = [
        '01KZETAAA50EMHCN6SP80T8DHC',
        '01KZETAAB4AW6M9ZFRB3X02CVW',
        '01KZETAAC103WZ24WQ7H087ZJ3',
        '01KZETAAD2EWE2H5ZV7WD8JWKT',
        '01KZETAAE63W6K93C55NCYXKVA',
    ];
    // Deliberately covers every applicability branch: single word vs phrase (word_bank), example
    // containing the answer vs not vs absent (cloze), and a mixed-case example (the match is
    // case-insensitive on both sides).
    $answers = [
        ['answer' => 'reservation', 'example' => 'I have a reservation for tonight.'],
        ['answer' => 'give up', 'example' => "I won't give up until I've achieved my goals."],
        ['answer' => 'front desk', 'example' => null],
        ['answer' => 'towel', 'example' => 'Could I have extra sheets, please?'],
        ['answer' => 'check in', 'example' => 'CHECK IN starts at 3 pm.'],
    ];

    $cases = [];
    foreach ($termIds as $t => $termId) {
        foreach ([0, 1, 2, 3, 7] as $cardIndex) {
            $cases[] = [
                'term_id' => $termId,
                'card_index' => $cardIndex,
                'answer' => $answers[$t]['answer'],
                'example' => $answers[$t]['example'],
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
    $shuffler = new ChipShuffler();
    $modes = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze'];
    $enabled = new EnabledModes(array_map(
        static fn (string $m): ExerciseMode => ExerciseMode::from($m),
        $modes,
    ));

    $cases = [];
    foreach (practiceContractCases() as $case) {
        $answer = $case['answer'];
        $example = $case['example'];
        // Exactly the derivation StudyCardAssembler does, so the port is pinned on these too —
        // word count and "can this example be blanked" are two more places to drift.
        $wordCount = $shuffler->wordCount($answer);
        $clozeable = $example !== null && $example !== '' && mb_stripos($example, $answer) !== false;

        $cases[] = [
            ...$case,
            'word_count' => $wordCount,
            'clozeable' => $clozeable,
            'rotation' => practiceRotation($case['term_id'], $case['card_index']),
            'expected_mode' => $this->selector->selectForPractice(
                $enabled,
                practiceRotation($case['term_id'], $case['card_index']),
                $wordCount,
                $clozeable,
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
