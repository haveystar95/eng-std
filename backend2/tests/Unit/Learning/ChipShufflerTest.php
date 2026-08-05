<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\ChipShuffler;
use Random\Engine\Mt19937;
use Random\Randomizer;

beforeEach(fn () => $this->shuffler = new ChipShuffler(new Randomizer(new Mt19937(1))));

it('scrambles a phrase into word chips, not in answer order', function () {
    $chips = $this->shuffler->chips('withdraw cash from account');

    $sorted = $chips;
    sort($sorted);

    expect($chips)->toHaveCount(4)
        ->and($sorted)->toBe(['account', 'cash', 'from', 'withdraw']) // same multiset
        ->and($chips)->not->toBe(['withdraw', 'cash', 'from', 'account']); // but reordered
});

it('splits a single word into shuffled letter chips', function () {
    $chips = $this->shuffler->chips('bank');

    $sorted = $chips;
    sort($sorted);

    expect($chips)->toHaveCount(4)
        ->and($sorted)->toBe(['a', 'b', 'k', 'n']);
});

it('leaves a single-letter answer untouched — nothing to shuffle', function () {
    expect($this->shuffler->chips('a'))->toBe(['a']);
});

it('adds 1–2 particle decoys for a phrasal verb, never the real particle', function () {
    $particles = ['up', 'on', 'in', 'off', 'out', 'down', 'over', 'away', 'back'];

    $chips = $this->shuffler->chips('give up', phrasalVerb: true);

    // The two real words survive, plus one or two decoy particles from the fixed set.
    expect($chips)->toContain('give')->toContain('up')
        ->and(count($chips))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(4);

    $decoys = array_values(array_diff($chips, ['give', 'up']));
    expect($decoys)->not->toBeEmpty()
        ->and($decoys)->not->toContain('up'); // the answer's own particle is never a decoy
    foreach ($decoys as $decoy) {
        expect($particles)->toContain($decoy);
    }
});

it('does not add decoys to a non-phrasal phrase', function () {
    $chips = $this->shuffler->chips('withdraw cash from account', phrasalVerb: false);

    expect($chips)->toHaveCount(4); // exactly the four real words, no decoys
});

it('counts whitespace-separated words for word_bank eligibility', function () {
    expect($this->shuffler->wordCount('bank'))->toBe(1)
        ->and($this->shuffler->wordCount('give up'))->toBe(2)
        ->and($this->shuffler->wordCount('  withdraw   cash from account '))->toBe(4);
});
