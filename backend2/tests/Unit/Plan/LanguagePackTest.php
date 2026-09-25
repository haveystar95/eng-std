<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Exception\LanguagePackKeyMissing;
use App\Modules\Shared\Domain\Service\SpeechMatch;

/**
 * WHAT ONE LANGUAGE PACK HANDS OUT (наряд LANG-1): its most frequent words, the template of a talk's title, what its
 * neighbours are told of it and what it is told of them, the one form its words are kept and asked in (`normal()`), and
 * the lists a comparison of speech reads, in the form of the text they meet (`speech()`, §4). The pack as a pack reads it
 * — no application; the en and ru packs of the deployment only where the test is about them.
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

// Canon (наряд LANG-1 §4): `normal()` folds as the kernel compares — composed, «ß» as «ss», «œ» as «oe», the cedilla
// letters with the comma below — before the case and the apostrophe; and it is ONE function for the list and for the
// word asked of it. CATCHES a model's «şi» (cedilla) that never meets the «și» of a Romanian list, a de «heißen» that
// never meets «heissen», a decomposed «é» that is not the composed one, and a fold on one side only (a list folded, the
// asked word not — or the other way round).
it('folds a word before lower-casing it, the same for the list and for the word asked of it', function () {
    expect(LanguagePack::normal(' Heißen '))->toBe('heissen')
        ->and(LanguagePack::normal('Œuf'))->toBe('oeuf')
        ->and(LanguagePack::normal('Şi'))->toBe('și')
        ->and(LanguagePack::normal('ţară'))->toBe('țară')
        ->and(LanguagePack::normal("e\u{0301}te\u{0301}"))->toBe('été')
        ->and(LanguagePack::normal('L’eau'))->toBe("l'eau");

    $ro = new LanguagePack('ro', ['common_words' => ['şi', 'în'], 'function_words' => ['și']]);
    $de = new LanguagePack('de', ['everyday_words' => ['heißen']]);

    expect($ro->commonWords())->toBe(['și', 'în'])
        ->and($ro->listed('common_words', 'și'))->toBeTrue()
        ->and($ro->listed('function_words', 'ŞI'))->toBeTrue()
        ->and($de->listed('everyday_words', 'Heissen'))->toBeTrue()
        ->and($de->listed('everyday_words', 'heißen'))->toBeTrue()
        ->and($de->words('everyday_words'))->toBe(['heissen']);
});

// Canon (наряд LANG-1 §4): «en и ru — байт в байт как были». Every string the en and ru packs write — list words, map keys
// and values, at any depth — is what `normal()` made of it before the fold. CATCHES a fold that moves an English or a
// Russian word (a «ё», a typographic apostrophe, a letter NFC would recompose) and with it every check that reads it.
it('normals every word of the English and the Russian pack as it did before the fold', function (string $code) {
    $strings = [];
    $walk = function (mixed $value) use (&$walk, &$strings): void {
        if (is_string($value)) {
            $strings[] = $value;
        } elseif (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $strings[] = $key;
                }
                $walk($item);
            }
        }
    };
    $walk(require dirname(__DIR__, 3)."/config/lesson/lang/{$code}.php");
    $before = static fn (string $word): string => str_replace('’', "'", mb_strtolower(trim($word)));

    expect(count($strings))->toBeGreaterThan(300)
        ->and(array_values(array_filter($strings, static fn (string $s): bool => LanguagePack::normal($s) !== $before($s))))->toBe([]);
})->with(['en', 'ru']);

/**
 * A PACK WRITTEN THE WAY ITS LANGUAGE WRITES — [code, the pack's keys, a text, its comparable words] — identical, row for
 * row, to the table of `mobile/test/data/plan/session/speech_match_test.dart` (the phone reads the same keys from the
 * `speech` block of the day).
 *
 * @return array<string, array{0: string, 1: array<string, mixed>, 2: string, 3: string}>
 */
function speechPackAsWritten(): array
{
    $de = ['number_words' => ['dreißig' => '30', 'Zwei' => '2']];
    $fr = ['number_words' => ['quatre-vingt-dix' => '90', 'vingt' => '20', 'un' => '1'], 'number_tens_joiners' => ['et']];
    $ro = ['number_words' => ['douăzeci' => '20', 'unu' => '1'], 'number_tens_joiners' => ['şi']];

    return [
        'de «dreißig» in the pack, «dreißig» said' => ['de', $de, 'dreißig', '30'],
        'de «dreißig» in the pack, «Dreissig» said' => ['de', $de, 'Dreissig Euro', '30 euro'],
        'de «Zwei» in the pack' => ['de', $de, 'zwei', '2'],
        'fr «quatre-vingt-dix» in the pack' => ['fr', $fr, 'quatre-vingt-dix', '90'],
        'fr «quatre-vingt-dix» said apart' => ['fr', $fr, 'quatre vingt dix', '90'],
        'fr «et» after the tens' => ['fr', $fr, 'vingt et un', '21'],
        'ro «şi» in the pack, «și» said' => ['ro', $ro, 'douăzeci și unu', '21'],
        'ro «şi» in the pack, «şi» said' => ['ro', $ro, 'douăzeci şi unu', '21'],
        'es capitals in the pack' => ['es', ['number_words' => ['Treinta' => '30', 'uno' => '1'], 'number_tens_joiners' => ['Y']], 'treinta y uno', '31'],
        'de two spellings of one entry — the first wins' => ['de', ['number_words' => ['dreißig' => '30', 'dreissig' => '31']], 'dreissig', '30'],
        'en «and» after a scale only' => ['en', ['number_words' => ['one' => '1', 'five' => '5', 'twenty' => '20', 'hundred' => '100'], 'number_joiners' => ['And']], 'twenty and five, one hundred and five', '20 and 5 105'],
    ];
}

// Canon (наряд LANG-1 §4): «speech() отдаёт списки в той же канонической форме, что и текст, с которым они сравниваются» —
// a pack writes its words the way its language does, and they meet the text. CATCHES an entry kept as written that no
// folded text can ever say («dreißig» against `dreissig`, «quatre-vingt-dix» — one word — against three, the «ş» of a
// joiner against the «ș» of a folded text), capitals kept, and two spellings of one entry fighting (the last one won).
it('hands down a pack written the way its language writes, in the form of the text it meets', function (string $code, array $keys, string $text, string $expected) {
    $speech = (new LanguagePack($code, $keys))->speech();

    expect(implode(' ', (new SpeechMatch)->words($text, $speech)))->toBe($expected);
})->with(speechPackAsWritten());

// Canon (наряд LANG-1 §4): every WORD list goes down canonical, each once, nothing empty, the first written first; the new
// `number_tens_joiners` is a list of its own (absent → none) and goes out on the wire only when there is one; the
// abbreviations keep their dots and case and only take the stored form of their letters. CATCHES a list handed down as
// written, an empty or doubled entry, a tens joiner read from `number_joiners` or served empty to every phone, and an
// abbreviation lower-cased or folded into something a text as written never holds.
it('puts every word list in the canonical form, keeps the abbreviations as written, serves the tens joiners only when there are some', function () {
    $pack = new LanguagePack('fr', [
        'unstressed_words' => ['L’', 'Du', 'du', "'", 'Jusqu’à'],
        'articles' => ['Le', 'LE', 'la'],
        'abbreviations' => ['M.', 'Mme', "e\u{0301}d."],
        'number_joiners' => ['Et'],
        'number_tens_joiners' => ['ET', 'et'],
    ]);
    $speech = $pack->speech();

    expect($speech->unstressed)->toBe(['l', 'du', 'jusquà'])
        ->and($speech->articles)->toBe(['le', 'la'])
        ->and($speech->abbreviations)->toBe(['M.', 'Mme', 'éd.'])
        ->and($speech->numberJoiners)->toBe(['et'])
        ->and($speech->numberTensJoiners)->toBe(['et'])
        ->and($speech->toArray()['number_tens_joiners'] ?? null)->toBe(['et'])
        ->and((new LanguagePack('en', ['number_joiners' => ['and']]))->speech()->numberTensJoiners)->toBe([])
        ->and((new LanguagePack('en', ['number_joiners' => ['and']]))->speech()->toArray())->not->toHaveKey('number_tens_joiners');
});

// Canon (наряд LANG-1 §4): «en и ru — байт в байт». Their packs are written in the canonical form already, so speech()
// hands down what they write, in their order — and no tens joiner. CATCHES a canonical form that moves an English or a
// Russian list (the phone would be served another block), and a tens joiner that appears for English.
it('hands down the English and the Russian lists exactly as they are written', function (string $code) {
    $data = require dirname(__DIR__, 3)."/config/lesson/lang/{$code}.php";
    $speech = lessonPacks()->for($code)->speech();

    expect($speech->unstressed)->toBe($data['unstressed_words'] ?? [])
        ->and($speech->articles)->toBe($data['articles'] ?? [])
        ->and($speech->abbreviations)->toBe($data['abbreviations'] ?? [])
        ->and($speech->numberWords)->toBe($data['number_words'] ?? [])
        ->and($speech->numberJoiners)->toBe($data['number_joiners'] ?? [])
        ->and($speech->numberTensJoiners)->toBe([])
        ->and(array_keys($speech->toArray()))->toBe(['unstressed_words', 'articles', 'abbreviations', 'number_words', 'number_joiners']);
})->with(['en', 'ru']);
