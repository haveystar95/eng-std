<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\SentenceTokenizer;

beforeEach(function () {
    $this->tokenizer = new SentenceTokenizer();
});

// ── the four rules, on the edge cases they were chosen for ───────────────────

it('keeps an in-word apostrophe inside its token', function () {
    // 10.8% of the corpus has one. "don" + "'t" is not a word anyone assembles.
    expect($this->tokenizer->tokenize("I won't give up until I've achieved my goals."))
        ->toBe(['I', "won't", 'give', 'up', 'until', "I've", 'achieved', 'my', 'goals']);

    expect($this->tokenizer->tokenize("That is the teacher's car."))
        ->toBe(['That', 'is', 'the', "teacher's", 'car']);
});

it('drops the final . ! ? — never a chip of its own', function () {
    // 99.3% of examples end with one, so a punctuation tile would be a constant in every card.
    expect($this->tokenizer->tokenize('I have a reservation for tonight.'))
        ->toBe(['I', 'have', 'a', 'reservation', 'for', 'tonight']);

    expect($this->tokenizer->tokenize('Where is the front desk?'))
        ->toBe(['Where', 'is', 'the', 'front', 'desk']);

    expect($this->tokenizer->tokenize('Watch out!'))->toBe(['Watch', 'out']);
    expect($this->tokenizer->tokenize('Really?!'))->toBe(['Really']);
});

it('keeps inner punctuation glued to its own word', function () {
    // 14.8% of examples have a comma; as its own chip it carries nothing and adds a position.
    expect($this->tokenizer->tokenize('Could I have extra sheets, please?'))
        ->toBe(['Could', 'I', 'have', 'extra', 'sheets,', 'please']);

    // A mid-sentence terminal mark is inner punctuation too — only the LAST one is stripped.
    expect($this->tokenizer->tokenize('Wow! That was close.'))
        ->toBe(['Wow!', 'That', 'was', 'close']);
});

it('does not fold case — the sentence keeps its own capitals', function () {
    // Grading normalises case, so this cannot make the task stricter; it keeps the chips literally
    // the pinned example's words, which is what makes the example the source of truth for order.
    expect($this->tokenizer->tokenize('CHECK IN starts at 3 pm.'))
        ->toBe(['CHECK', 'IN', 'starts', 'at', '3', 'pm']);
});

// ── shape ────────────────────────────────────────────────────────────────────

it('collapses any run of whitespace and ignores surrounding space', function () {
    expect($this->tokenizer->tokenize("  She\tgoes   to\nwork.  "))
        ->toBe(['She', 'goes', 'to', 'work']);
});

it('survives degenerate input instead of emitting an empty chip', function () {
    expect($this->tokenizer->tokenize(''))->toBe([]);
    expect($this->tokenizer->tokenize('   '))->toBe([]);
    expect($this->tokenizer->tokenize('!'))->toBe([]);
    expect($this->tokenizer->tokenize('Hello .'))->toBe(['Hello']);
});

it('counts the chips a sentence yields — what the gate measures', function () {
    // The count is of TOKENS, not of raw words: the dropped full stop must not buy a chip.
    expect($this->tokenizer->count('I have a reservation for tonight.'))->toBe(6)
        ->and($this->tokenizer->count('Please hurry up.'))->toBe(3)
        ->and($this->tokenizer->count(''))->toBe(0);
});

it('recognises an example that is merely the term itself, ignoring case and the full stop', function () {
    expect($this->tokenizer->sameTokens('Nice to meet you.', 'nice to meet you'))->toBeTrue()
        ->and($this->tokenizer->sameTokens('Give up!', 'give up'))->toBeTrue()
        ->and($this->tokenizer->sameTokens('Never give up.', 'give up'))->toBeFalse()
        ->and($this->tokenizer->sameTokens('I have a reservation.', 'reservation'))->toBeFalse();
});
