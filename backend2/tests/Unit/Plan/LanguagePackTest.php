<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Exception\LanguagePackKeyMissing;

/**
 * WHAT ONE LANGUAGE PACK HANDS OUT (наряд LANG-1): its most frequent words, the template of a talk's title, what its
 * neighbours are told of it and what it is told of them. The pack as a pack reads it — no config, no application.
 */

// Canon (LANG-1 §5): a pack built the old way — code and keys, nothing more — keeps working and knows no neighbours.
// CATCHES a new constructor argument that breaks `new LanguagePack('xx', [...])` or `LanguagePack::none()`.
it('builds from its code and keys alone and then knows no neighbours', function () {
    $pack = new LanguagePack('en', ['rescue_line' => 'Sorry?']);

    expect($pack->rescueLine())->toBe('Sorry?')
        ->and($pack->neighbours())->toBe([])
        ->and(LanguagePack::none('pl')->neighbours())->toBe([])
        ->and(LanguagePack::none('pl')->commonWords())->toBe([]);
});

// Canon (LANG-1 §5, key `common_words`): lower-cased as `normal` gives them, each once; an empty list when the pack does
// not write the key, or writes it null. CATCHES a list read as written — «Nie» never meeting the line's «nie» — or a
// missing key throwing where the guard only wants to know there is nothing to compare.
it('hands out its frequent words lower-cased, once each, and none when it does not write them', function () {
    $pack = new LanguagePack('pl', ['common_words' => ['Nie', 'się', 'nie', ' W ', 'I']]);

    expect($pack->commonWords())->toBe(['nie', 'się', 'w', 'i'])
        ->and((new LanguagePack('pl', ['common_words' => null]))->commonWords())->toBe([])
        ->and((new LanguagePack('pl', []))->commonWords())->toBe([]);
});

// Canon (LANG-1 §5): the other packs are told the letters as the pack WRITES them — the pattern string itself, which is
// what «the same script» is read on — and its frequent words; a pack that does not write its letters is told as null.
// CATCHES a neighbour whose letters are dropped or compiled into something two identical packs would not share.
it('tells its neighbours its letters as written and its frequent words', function () {
    $pack = new LanguagePack('fr', ['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['Je', 'de', 'est']]);

    expect($pack->asNeighbour())->toBe(['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['je', 'de', 'est']])
        ->and((new LanguagePack('en', ['script_letters' => null]))->asNeighbour())->toBe(['script_letters' => null, 'common_words' => []])
        ->and(LanguagePack::none('it')->asNeighbour())->toBe(['script_letters' => null, 'common_words' => []]);
});

// Canon (LANG-1 §5): the neighbours given are kept, by code — never the pack itself. CATCHES a pack compared with its own
// words (nothing only-its-own, nothing only-theirs: the guard would never refuse) or a `none` pack that drops them.
it('keeps the neighbours it is given, never itself', function () {
    $neighbours = [
        'en' => ['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['the', 'to']],
        'pl' => ['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['nie', 'się']],
    ];

    expect((new LanguagePack('pl', [], $neighbours))->neighbours())->toBe(['en' => $neighbours['en']])
        ->and(LanguagePack::none('de', $neighbours)->neighbours())->toBe($neighbours);
});

// Canon (LANG-1, key `talk_title_template`): null when the pack does not write it or writes the no-op `[]` (ru, uk, en —
// the code builds their titles); the four fields when it does. CATCHES a template handed out half-read, or a missing or
// no-op one throwing where the title falls back.
it('hands out the template of a talk\'s title, or null when it does not write one', function () {
    $template = ['title' => 'Rozmowa: {roles}', 'and' => 'i', 'anyone' => 'Rozmowa', 'lower_first' => true];

    expect((new LanguagePack('pl', ['talk_title_template' => $template]))->talkTitleTemplate())->toBe($template)
        ->and((new LanguagePack('de', ['talk_title_template' => [...$template, 'lower_first' => false]]))->talkTitleTemplate()['lower_first'] ?? null)->toBeFalse()
        ->and((new LanguagePack('ru', ['talk_title_template' => []]))->talkTitleTemplate())->toBeNull()
        ->and((new LanguagePack('pl', ['talk_title_template' => null]))->talkTitleTemplate())->toBeNull()
        ->and(LanguagePack::none('pl')->talkTitleTemplate())->toBeNull();
});

// Canon (LANG-1): a template written wrong is the pack's bug and names its field. CATCHES a title with no place for its
// roles, a missing or blank joiner (the title falls back to English on it without a word), a blank `anyone` or a
// `lower_first` written as a string handed out as if it were a template.
it('throws on a template written wrong, naming the field', function (array $template, string $field) {
    expect(fn () => (new LanguagePack('pl', ['talk_title_template' => $template]))->talkTitleTemplate())
        ->toThrow(LanguagePackKeyMissing::class, "«{$field}»");
})->with([
    'no {roles}' => [['title' => 'Rozmowa', 'and' => 'i', 'anyone' => 'Rozmowa', 'lower_first' => true], 'talk_title_template.title'],
    'no and' => [['title' => 'Rozmowa: {roles}', 'anyone' => 'Rozmowa', 'lower_first' => true], 'talk_title_template.and'],
    'no anyone' => [['title' => 'Rozmowa: {roles}', 'and' => 'i', 'lower_first' => true], 'talk_title_template.anyone'],
    'a blank and' => [['title' => 'Rozmowa: {roles}', 'and' => ' ', 'anyone' => 'Rozmowa', 'lower_first' => true], 'talk_title_template.and'],
    'a blank anyone' => [['title' => 'Rozmowa: {roles}', 'and' => 'i', 'anyone' => '', 'lower_first' => true], 'talk_title_template.anyone'],
    'lower_first as a string' => [['title' => 'Rozmowa: {roles}', 'and' => 'i', 'anyone' => 'Rozmowa', 'lower_first' => 'yes'], 'talk_title_template.lower_first'],
]);
