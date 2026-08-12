<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\LanguagePurity;

beforeEach(fn () => $this->purity = new LanguagePurity());

it('names the Ukrainian-only letters found in a Russian field', function () {
    // The live defect: «на одній хвилі, розуміти одне одного» shipped as a Russian translation.
    expect($this->purity->foreignLetters('ru', 'на одній хвилі, розуміти одне одного'))->toBe(['і']);
});

it('passes an honest Russian string', function () {
    expect($this->purity->foreignLetters('ru', 'быть на одной волне'))->toBe([]);
    expect($this->purity->isClean('ru', 'быть на одной волне'))->toBeTrue();
});

it('reports each offending letter once, in order', function () {
    expect($this->purity->foreignLetters('ru', 'Важливо, щоб команда розуміла одне одного щодо цілей проєкту.'))
        ->toBe(['і', 'є']);
});

/**
 * The half this class cannot see, asserted so nobody mistakes a green check for a clean field.
 * «здаватися» is Ukrainian spelled entirely in letters Russian also has — the enrichment model's
 * lexis notes are what catch this class, not the character scan.
 */
it('does NOT catch Ukrainian written in shared letters', function () {
    expect($this->purity->isClean('ru', 'здаватися'))->toBeTrue();
});

it('flags Cyrillic that leaked into an English field', function () {
    expect($this->purity->foreignLetters('en', 'I would like to снять cash'))->toBe(['с', 'н', 'я', 'т', 'ь']);
});

it('passes English with punctuation, digits and an apostrophe', function () {
    expect($this->purity->isClean('en', "I'd like to withdraw $200, please."))->toBeTrue();
});

/**
 * Silence, not a guess. A caller treats an empty list as "write it" — which is the right default
 * for a check that only knows two languages, and the reason a German field is not rejected here.
 */
it('has no opinion about a language it does not police', function () {
    expect($this->purity->foreignLetters('de', 'Kann ich mit Karte bezahlen?'))->toBe([]);
    expect($this->purity->foreignLetters('', 'на одній хвилі'))->toBe([]);
});

it('reads the language code case- and whitespace-insensitively', function () {
    expect($this->purity->foreignLetters(' RU ', 'на одній хвилі'))->toBe(['і']);
});
