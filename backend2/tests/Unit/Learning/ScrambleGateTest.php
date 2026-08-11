<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\ChipShuffler;
use App\Modules\Learning\Domain\Service\PlayabilityAssessor;
use App\Modules\Learning\Domain\Service\SentenceTokenizer;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\TermPlayability;

beforeEach(function () {
    $this->assess = new PlayabilityAssessor(new ChipShuffler(), new SentenceTokenizer());
});

/** Does the assessed term admit a scramble card? */
function scrambles(PlayabilityAssessor $assess, string $answer, ?string $example, ?string $translation = 'Перевод примера.'): bool
{
    return $assess->assess($answer, $example, $translation)->supports(ExerciseMode::Scramble);
}

it('admits an example inside the 4…12 window', function () {
    // 6 tokens — the shape 96% of the corpus has.
    expect(scrambles($this->assess, 'reservation', 'I have a reservation for tonight.'))->toBeTrue();
});

it('refuses a sentence below the floor — three chips is six orders, not an exercise', function () {
    expect(scrambles($this->assess, 'hurry', 'Please hurry up.'))->toBeFalse()          // 3
        ->and(scrambles($this->assess, 'run', 'I run.'))->toBeFalse();                  // 2
});

it('admits exactly at both edges of the window and refuses just outside', function () {
    $of = fn (int $words): string => implode(' ', array_fill(0, $words, 'word')).'.';

    expect(scrambles($this->assess, 'word', $of(3)))->toBeFalse()
        ->and(scrambles($this->assess, 'word', $of(4)))->toBeTrue()   // MIN
        ->and(scrambles($this->assess, 'word', $of(12)))->toBeTrue()  // MAX
        ->and(scrambles($this->assess, 'word', $of(13)))->toBeFalse();
});

it('refuses a sentence above the ceiling — a wall of tiles for one mistake', function () {
    expect(scrambles(
        $this->assess,
        'suitcase',
        'I left my suitcase at the hotel and had to go back for it in the evening.', // 16
    ))->toBeFalse();
});

it('refuses a term with no example at all', function () {
    expect(scrambles($this->assess, 'front desk', null))->toBeFalse()
        ->and(scrambles($this->assess, 'front desk', ''))->toBeFalse();
});

it('refuses an untranslated example — the translation IS the question', function () {
    $sentence = 'I have a reservation for tonight.';

    expect(scrambles($this->assess, 'reservation', $sentence, null))->toBeFalse()
        ->and(scrambles($this->assess, 'reservation', $sentence, '   '))->toBeFalse()
        ->and(scrambles($this->assess, 'reservation', $sentence, 'У меня бронь на сегодня.'))->toBeTrue();
});

it('refuses an example that is merely the term itself — that is word_bank, twice', function () {
    // A phrase whose "example" is the phrase would deal the same tiles against the same target.
    expect(scrambles($this->assess, 'Nice to meet you', 'Nice to meet you.'))->toBeFalse()
        ->and(scrambles($this->assess, 'nice to meet you', 'NICE TO MEET YOU!'))->toBeFalse();

    // The same phrase inside a real sentence is a perfectly good scramble.
    expect(scrambles($this->assess, 'Nice to meet you', 'Nice to meet you, I am Denis.'))->toBeTrue();
});

it('gates scramble independently of cloze — the two ask different questions', function () {
    // An example that does NOT contain the term cannot be blanked, but can still be assembled.
    $playable = $this->assess->assess('towel', 'Could I have extra sheets, please?', 'Можно ещё простыни?');

    expect($playable->supports(ExerciseMode::Cloze))->toBeFalse()
        ->and($playable->supports(ExerciseMode::Scramble))->toBeTrue();
});

it('never gates the modes that ask for the term itself', function () {
    // A bare term with nothing attached still plays — just not in the data-hungry modes.
    $bare = new TermPlayability(answerWordCount: 1, clozeable: false);

    expect($bare->supports(ExerciseMode::MultipleChoice))->toBeTrue()
        ->and($bare->supports(ExerciseMode::Typing))->toBeTrue()
        ->and($bare->supports(ExerciseMode::Listening))->toBeTrue()
        ->and($bare->supports(ExerciseMode::WordBank))->toBeFalse()
        ->and($bare->supports(ExerciseMode::Cloze))->toBeFalse()
        ->and($bare->supports(ExerciseMode::Scramble))->toBeFalse();
});

// ── chips ────────────────────────────────────────────────────────────────────

it('deals the sentence own tokens as chips, never in the sentence order', function () {
    $chips = (new ChipShuffler())->sentenceChips('I have a reservation for tonight.');

    expect($chips)->toEqualCanonicalizing(['I', 'have', 'a', 'reservation', 'for', 'tonight'])
        ->and($chips)->not->toBe(['I', 'have', 'a', 'reservation', 'for', 'tonight'])
        ->and($chips)->not->toContain('tonight.'); // the full stop never becomes a tile
});

it('adds no decoy tiles to a sentence', function () {
    // word_bank slips decoy particles into a phrasal verb; on a sentence that would change the
    // exercise from "recall the order" into "spot the intruder".
    $chips = (new ChipShuffler())->sentenceChips("I won't give up until I've achieved my goals.");

    expect($chips)->toHaveCount(9);
});
