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

/**
 * The other half of "wrong language", found live AFTER the letter check had been applied: a German
 * example translation sitting in a Russian field, sharing no letter with Ukrainian.
 */
it('flags a Russian field written wholesale in another script', function () {
    expect($this->purity->isWrongScript('ru', 'Kann ich mit Karte bezahlen, oder nur bar?'))->toBeTrue()
        ->and($this->purity->isClean('ru', 'Kann ich mit Karte bezahlen, oder nur bar?'))->toBeFalse();
});

/**
 * The reason it is a majority and not "any Latin letter". Russian carries these legitimately, and a
 * check that rejected them would reject correct content — the failure mode that gets a barrier
 * switched off.
 */
it('leaves a Latin borrowing inside a Russian phrase alone', function () {
    expect($this->purity->isClean('ru', 'пароль от Wi-Fi'))->toBeTrue()
        ->and($this->purity->isClean('ru', 'подключиться к Wi-Fi'))->toBeTrue()
        // A tie is not a majority: «сеть Wi-Fi» is 4 Cyrillic letters against 4 Latin.
        ->and($this->purity->isClean('ru', 'сеть Wi-Fi'))->toBeTrue();
});

it('does not judge the script of a language it does not police', function () {
    expect($this->purity->isWrongScript('de', 'Kann ich mit Karte bezahlen?'))->toBeFalse()
        // No letters at all is not a wrong script.
        ->and($this->purity->isWrongScript('ru', '12:30 — 15%'))->toBeFalse();
});

it('reads the language code case- and whitespace-insensitively', function () {
    expect($this->purity->foreignLetters(' RU ', 'на одній хвилі'))->toBe(['і']);
});

/**
 * The mirror (DECISIONS п. 91 — «суржик ловится в обе стороны»).
 *
 * Only the ru direction existed, which is a strange asymmetry for a symmetric problem: the content
 * that existed was Russian, so Ukrainian leaking INTO it was the only failure anyone had seen.
 * Ukrainian is a support language, so the other direction is content somebody will actually read.
 */
it('names the Russian-only letters found in a Ukrainian field', function () {
    expect($this->purity->foreignLetters('uk', 'быть на одной волне'))->toBe(['ы'])
        ->and($this->purity->isClean('uk', 'быть на одной волне'))->toBeFalse();
});

it('catches э, ё and ъ in a Ukrainian field too', function () {
    expect($this->purity->foreignLetters('uk', 'это ещё съезд'))->toBe(['э', 'ё', 'ъ']);
});

it('passes an honest Ukrainian string', function () {
    expect($this->purity->foreignLetters('uk', 'на одній хвилі, розуміти одне одного'))->toBe([])
        ->and($this->purity->isClean('uk', 'на одній хвилі, розуміти одне одного'))->toBeTrue();
});

it('catches суржик in both directions with the same detector', function () {
    // One string, two verdicts — and each is right about the field it is asked about.
    expect($this->purity->isClean('ru', 'на одній хвилі'))->toBeFalse()
        ->and($this->purity->isClean('uk', 'на одній хвилі'))->toBeTrue()
        ->and($this->purity->isClean('uk', 'быть на одной волне'))->toBeFalse()
        ->and($this->purity->isClean('ru', 'быть на одной волне'))->toBeTrue();
});

/**
 * The mirror inherits the mirror of the limitation, and it is worth stating: Russian written
 * entirely in letters Ukrainian also has passes. «робота» is a Ukrainian word, «работа» is Russian
 * — and neither uses a letter the other lacks, so only a reader who knows both can tell.
 */
it('does NOT catch Russian written in shared letters', function () {
    expect($this->purity->isClean('uk', 'сейчас'))->toBeTrue();
});
