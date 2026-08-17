<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\DistractorRepair;

beforeEach(fn () => $this->repair = new DistractorRepair());

it('derives the span and correction for a one-word substitution', function () {
    expect($this->repair->derive(
        'I think I have the cold.',
        'I think I have a cold.',
    ))->toBe(['span' => 'the', 'correction' => 'a']);
});

it('widens an insertion to the left, so the span is something that can be underlined', function () {
    // The distractor is missing a word: the differing region is empty on its side, and an empty span
    // cannot be shown to anyone. One token of context on each side makes both sides real.
    expect($this->repair->derive(
        'I need to fill prescription for my medication.',
        'I need to fill a prescription for my medication.',
    ))->toBe(['span' => 'fill', 'correction' => 'fill a']);
});

it('widens to the right when the difference is at the very start', function () {
    expect($this->repair->derive(
        'Running a temperature since last night.',
        'He has been running a temperature since last night.',
    ))->not->toBeNull();
});

it('refuses a row that breaks two separate places', function () {
    // Two disjoint regions — «has»/«have» and the missing clause. No single underline describes it,
    // and our own rule says a distractor changes exactly one thing, so this is not repairable.
    expect($this->repair->derive(
        'I has experience with agile methodologies.',
        'I have experience with agile methodologies, which helps in adapting to changes quickly.',
    ))->toBeNull();
});

it('refuses a row identical to the example — there is no region at all', function () {
    expect($this->repair->derive('I have a cold.', 'I have a cold.'))->toBeNull();
});

it('does not invent a region from punctuation alone', function () {
    // Tokens are compared through the normaliser, so «cold» and «cold.» are one token.
    expect($this->repair->derive('I have a cold', 'I have a cold.'))->toBeNull();
});

it('keeps the original spelling in the span, not the normalised one', function () {
    // The span is underlined in the sentence as written, so it has to come from the sentence as
    // written — the normaliser decides token IDENTITY, never the text that is stored.
    expect($this->repair->derive(
        'He started going bald in his thirty years.',
        'He started going bald in his thirties.',
    ))->toBe(['span' => 'thirty years.', 'correction' => 'thirties.']);
});
