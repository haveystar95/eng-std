<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\TermOccurrence;

/**
 * One criterion, two sides. The server refuses an example that does not contain its term; the
 * client's intro card bolds the term inside the example it is given. Both ask this class's question
 * (the client's copy is `termSearchForm` + `spanPositionIn`), and the day they answer differently is
 * the day the server accepts a sentence the card renders flat.
 */
it('finds a plain word in its example', function () {
    expect(TermOccurrence::inExample('We only buy organic food for our dog.', 'organic'))->toBeTrue();
});

it('ignores case, like the card does', function () {
    expect(TermOccurrence::inExample('Organic food is dearer.', 'organic food'))->toBeTrue();
});

it('lets a sentence-like term shed its own trailing mark', function () {
    expect(TermOccurrence::inExample('I have a fever and feel very weak.', 'I have a fever.'))->toBeTrue();
    expect(TermOccurrence::inExample('How much does this bag cost if I take two?', 'How much does this bag cost?'))->toBeTrue();
});

it('says no when the term is simply not there', function () {
    // The sentence that shipped: a different object, a different question.
    expect(TermOccurrence::inExample('How much does that coat cost?', 'How much does this bag cost?'))->toBeFalse();
});

it('normalises only the TAIL — inner punctuation is part of the term', function () {
    expect(TermOccurrence::searchForm('Well, thanks!'))->toBe('Well, thanks');
    expect(TermOccurrence::searchForm('grain-free'))->toBe('grain-free');
    expect(TermOccurrence::searchForm('fever'))->toBe('fever');
});

it('never degrades into a check that passes everything', function () {
    // A term of pure punctuation would normalise to '', which every string "contains".
    expect(TermOccurrence::searchForm('?!'))->toBe('?!');
    expect(TermOccurrence::inExample('Any sentence at all.', '?!'))->toBeFalse();
});
