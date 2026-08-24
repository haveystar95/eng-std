<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\TextNormalizer;

beforeEach(fn () => $this->n = new TextNormalizer());

/**
 * The canonical form is what gets STORED, so every case here is about content coming out looking
 * like itself — no folding, no lowercasing, no losing a letter.
 */
it('composes decomposed sequences into one code point', function (string $input, string $expected) {
    expect($this->n->canonical($input))->toBe($expected);
})->with([
    // s + COMBINING COMMA BELOW → ș (one code point)
    'ro comma-below, decomposed' => ["s\u{0326}tiu", "\u{0219}tiu"],
    // s + COMBINING CEDILLA composes to ş, which then canonicalises to ș
    'ro cedilla, decomposed' => ["s\u{0327}tiu", "\u{0219}tiu"],
    // e + COMBINING ACUTE → é
    'fr accent, decomposed' => ["cafe\u{0301}", "caf\u{00E9}"],
    'already composed is left alone' => ["caf\u{00E9}", "caf\u{00E9}"],
]);

it('writes Romanian with the comma below, not the cedilla', function (string $input, string $expected) {
    expect($this->n->canonical($input))->toBe($expected);
})->with([
    'lowercase s' => ["\u{015F}tiu", "\u{0219}tiu"],          // ştiu → știu
    'lowercase t' => ["a\u{0163}i", "a\u{021B}i"],            // aţi  → ați
    'uppercase S' => ["\u{015E}tiu", "\u{0218}tiu"],          // Ştiu → Știu
    'uppercase T' => ["\u{0162}ara", "\u{021A}ara"],          // Ţara → Țara
    'already canonical' => ["\u{0219}tiu", "\u{0219}tiu"],
]);

it('leaves the letters that fold only for comparison alone when storing', function () {
    // ß and œ are CONTENT. They are equivalences for a grader, never a rewrite of what is stored:
    // «Straße» printed on a card as «Strasse» would be teaching the wrong spelling.
    expect($this->n->canonical('Straße'))->toBe('Straße')
        ->and($this->n->canonical('cœur'))->toBe('cœur');
});

it('is idempotent — canonical text survives another pass unchanged', function (string $input) {
    $once = $this->n->canonical($input);
    expect($this->n->canonical($once))->toBe($once);
})->with(['ştiu', 'Straße', 'cœur', 'быть на одной волне', 'I need to withdraw cash.']);

/**
 * The fold form is what gets COMPARED, and never stored.
 */
it('folds the spellings a learner may legitimately produce either of', function (string $a, string $b) {
    expect($this->n->fold($a))->toBe($this->n->fold($b));
})->with([
    'ro cedilla ≡ comma-below' => ["\u{015F}tiu", "\u{0219}tiu"],
    'ro uppercase' => ["\u{015E}tiu", "\u{0218}tiu"],
    'ro t' => ["a\u{0163}i", "a\u{021B}i"],
    'ß ≡ ss' => ['Straße', 'Strasse'],
    'œ ≡ oe' => ['cœur', 'coeur'],
    'NFC composition' => ["cafe\u{0301}", "caf\u{00E9}"],
]);

it('does not fold away distinctions that are not equivalences', function () {
    // Diacritics are not stripped: `fold` resolves the ways ONE letter can be written, not the
    // difference between two letters. Which diacritics a language forgives is the grader's
    // strictness rule (DECISIONS п. 86), a different question with a different answer per language.
    expect($this->n->fold('știi'))->not->toBe($this->n->fold('stii'))
        ->and($this->n->fold('café'))->not->toBe($this->n->fold('cafe'));
});
