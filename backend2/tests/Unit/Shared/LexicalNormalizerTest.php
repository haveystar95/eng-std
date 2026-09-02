<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\LexicalNormalizer;

beforeEach(fn () => $this->normalizer = new LexicalNormalizer());

// ---- the perfect auxiliary: the one place where 's and 'd are not a guess -----------------------

it('reads «he\'s been» as «he has been», not «he is been»', function () {
    // Live evidence: the станок offered «He has been running a temperature since last night.» as a
    // WRONG answer for the example «He's been running a temperature since last night.» — the same
    // sentence, because these two spellings did not fold together.
    expect($this->normalizer->normalize("He's been running a temperature since last night."))
        ->toBe($this->normalizer->normalize('He has been running a temperature since last night.'));
});

it('reads «\'d been» as «had been»', function () {
    expect($this->normalizer->normalize("I'd been waiting for an hour."))
        ->toBe($this->normalizer->normalize('I had been waiting for an hour.'));
});

it('lets the rule beat the curated map, which would otherwise say «it is been»', function () {
    expect($this->normalizer->normalize("It's been a long day."))
        ->toBe($this->normalizer->normalize('It has been a long day.'));
});

it('applies the rule to any subject, not to a list of pronouns', function () {
    expect($this->normalizer->normalize("The delivery's been late twice."))
        ->toBe($this->normalizer->normalize('The delivery has been late twice.'));
});

it('still reads a bare «\'d» as «would», where the grammar decides nothing', function () {
    expect($this->normalizer->normalize("I'd like the pasta."))
        ->toBe($this->normalizer->normalize('I would like the pasta.'));
});

it('folds the typographic apostrophe onto the ASCII one', function () {
    expect($this->normalizer->normalize('I’d like the pasta for go, please.'))
        ->toBe($this->normalizer->normalize("I'd like the pasta for go, please."));
});

// ---- the apostrophe joins letters, it does not separate words (Д-32) ---------------------------

it('reads «He\'s five years old.» and «Hes five years old.» as one answer', function () {
    // The live typing card. «He's» is outside the curated contraction map, so the apostrophe reached
    // the punctuation pass and became a SPACE: «he s five years old» against the typed «hes five
    // years old» — one edit apart, so an exact answer came back «Почти» and the schedule took a
    // `hard` for it.
    expect($this->normalizer->normalize("He's five years old."))
        ->toBe($this->normalizer->normalize('Hes five years old.'));
});

it('accepts the typographic apostrophe on a word the contraction map has never heard of', function () {
    // The phone types «’» and the store holds «'». Neither is a different answer.
    expect($this->normalizer->normalize('He’s five years old.'))
        ->toBe($this->normalizer->normalize("He's five years old."));
});

it('keeps the apostrophe where the mark itself is the difference under examination', function () {
    // A possessive against a plural is a real correction, and the enrichment validator's «this
    // corrects nothing» gate has to be able to see it.
    expect($this->normalizer->canonicalizeKeepingApostrophe('accounts'))
        ->not->toBe($this->normalizer->canonicalizeKeepingApostrophe("account's"))
        ->and($this->normalizer->canonicalizeKeepingApostrophe('account’s'))
        ->toBe($this->normalizer->canonicalizeKeepingApostrophe("account's"));
});

// ---- canonicalize vs normalize: whether the leading article is under examination ----------------

it('drops the leading article in normalize — the answer key is indifferent to it', function () {
    expect($this->normalizer->normalize('a bank account'))->toBe('bank account');
});

it('keeps the leading article in canonicalize — an article correction lives on that difference', function () {
    // «bank account» → «a bank account» is the `article` class doing its job. Through normalize()
    // the two sides are identical and every such correction would read as a no-op.
    expect($this->normalizer->canonicalize('a bank account'))->toBe('a bank account')
        ->and($this->normalizer->canonicalize('bank account'))->toBe('bank account');
});

it('canonicalizes everything else exactly as normalize does', function () {
    expect($this->normalizer->canonicalize("  It's   READY, now.  "))->toBe('it is ready now');
});
