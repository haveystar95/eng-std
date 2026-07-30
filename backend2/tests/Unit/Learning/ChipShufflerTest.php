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
