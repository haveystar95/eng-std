<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Service\FrameWords;

/**
 * THE WORDS THE JUDGE OF THE CONSTRUCTIONS COMPARES (наряд FIX-4 §2; наряд LANG-1 §1) — one form for the frame and the
 * move: folded, lower case, contractions spelt out, ELISIONS spelt out (a `contractions` key that ends with an
 * apostrophe is a prefix), articles out. The French and Italian packs are built here in the shape the order fixes; the
 * English one is the deployment's own.
 */

/** @param array<string, mixed> $keys */
function fwPack(string $code, array $keys): LanguagePack
{
    return new LanguagePack($code, ['sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'], ...$keys]);
}

function fwFrench(): LanguagePack
{
    return fwPack('fr', [
        'contractions' => [
            "j'" => 'je', "n'" => 'ne', "l'" => 'le', "d'" => 'de', "s'" => 'se', "jusqu'" => 'jusque', 'qu’' => 'que',
            // A whole word the pack spells out is read before any elision it starts with.
            "s'il" => 'si il',
        ],
        'articles' => ['le', 'la', 'les', 'un', 'une'],
    ]);
}

function fwItalian(): LanguagePack
{
    return fwPack('it', [
        'contractions' => ["l'" => 'lo', "un'" => 'una', "dell'" => 'dello', "all'" => 'allo'],
        'articles' => ['il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una'],
    ]);
}

// Canon (наряд LANG-1 §1): «запись contractions с ключом, оканчивающимся апострофом, — ПРЕФИКСНАЯ элизия: слово, которое
// начинается этим префиксом и имеет буквы после него, читается как значение записи плюс остаток; ' и ’ оба». CATCHES an
// elision read as one word («jai»), the typographic apostrophe of a phone or of the pack not read as one, a whole-word
// entry cut at its elision («s'il» as «se il»), and a word with an apostrophe inside cut where no elision is written.
it('spells a French elision out as its two words, before the apostrophe is deleted', function () {
    $fr = fwFrench();

    expect(FrameWords::of("J'ai", $fr, articles: true))->toBe(['je', 'ai'])
        ->and(FrameWords::of('Je n’ai pas de fièvre', $fr))->toBe(['je', 'ne', 'ai', 'pas', 'de', 'fièvre'])
        ->and(FrameWords::of("l'hôpital", $fr, articles: true))->toBe(['le', 'hôpital'])
        // The elided article is an article: out of the comparison unless asked for.
        ->and(FrameWords::of("à l'hôpital", $fr))->toBe(['à', 'hôpital'])
        ->and(FrameWords::of("jusqu'à demain", $fr))->toBe(['jusque', 'à', 'demain'])
        // A key the pack writes with the typographic apostrophe is the same key.
        ->and(FrameWords::of("qu'il vienne", $fr))->toBe(['que', 'il', 'vienne'])
        ->and(FrameWords::of("s'il vous plaît", $fr))->toBe(['si', 'il', 'vous', 'plaît'])
        ->and(FrameWords::of("Il s'appelle Paul", $fr))->toBe(['il', 'se', 'appelle', 'paul'])
        // No elision is written for «aujourd'»: the apostrophe joins its letters, as before.
        ->and(FrameWords::of("aujourd'hui", $fr))->toBe(['aujourdhui'])
        // An elision written apart from its word (a recogniser's «l' hôpital») is the whole-word entry.
        ->and(FrameWords::of("l' hôpital", $fr, articles: true))->toBe(['le', 'hôpital'])
        // A prefix with no letter after it is no elision.
        ->and(FrameWords::of("d'1", $fr))->toBe(['d1']);
});

// Canon (наряд LANG-1 §1): it «l'» => 'lo', «un'» => 'una', «dell'» => 'dello'. CATCHES an elided preposition with its
// article read as one word («dellospedale»), and the article of an elision kept in the comparison.
it('spells an Italian elision out, the preposition with its article too', function () {
    $it = fwItalian();

    expect(FrameWords::of("dell'ospedale", $it, articles: true))->toBe(['dello', 'ospedale'])
        ->and(FrameWords::of("un'ora", $it, articles: true))->toBe(['una', 'ora'])
        ->and(FrameWords::of("Vado all'ospedale tra un'ora", $it))->toBe(['vado', 'allo', 'ospedale', 'tra', 'ora'])
        ->and(FrameWords::of("L'ho visto", $it, articles: true))->toBe(['lo', 'ho', 'visto']);
});

// Canon (наряд FIX-4 §2, «en contractions unchanged»): the English contractions are whole words and read as before — no
// key of the English pack ends with an apostrophe, so no word of English is cut at one. CATCHES the elision rule reaching
// into English («o'clock», «rock'n'roll»), and «he's been» losing its «has».
it('reads English contractions as before', function () {
    $en = lessonPacks()->for('en');

    expect(FrameWords::of("I'm working on", $en))->toBe(['i', 'am', 'working', 'on'])
        ->and(FrameWords::of('I don’t have a fever', $en))->toBe(['i', 'do', 'not', 'have', 'fever'])
        ->and(FrameWords::of("He's been sick", $en))->toBe(['he', 'has', 'been', 'sick'])
        ->and(FrameWords::of("He's sick", $en))->toBe(['he', 'is', 'sick'])
        ->and(FrameWords::of("at five o'clock", $en))->toBe(['at', 'five', 'oclock'])
        ->and(FrameWords::of("rock'n'roll", $en))->toBe(['rocknroll'])
        ->and(FrameWords::of('I cannot come', $en))->toBe(['i', 'can', 'not', 'come']);
});

// Canon (наряд LANG-1 §1, the kernel's comparison form, DECISIONS п. 87): ß is ss, œ is oe, the Romanian cedilla letters
// are the comma-below ones, a decomposed letter is composed — on both sides; the written word the value is read back from
// stays as it was said. CATCHES a recogniser's «Strasse», «coeur» or «şi» not meeting the frame's word.
it('reads every word in one spelling of its letters, and keeps the written word as said', function () {
    $bare = fwPack('xx', []);
    $decomposed = "cafe\u{0301}";

    expect(FrameWords::of('Straße', $bare))->toBe(['strasse'])
        ->and(FrameWords::of('STRASSE', $bare))->toBe(['strasse'])
        ->and(FrameWords::of('cœur', $bare))->toBe(['coeur'])
        ->and(FrameWords::of("A\u{015F}a \u{015F}i a\u{0219}a", $bare))->toBe(["a\u{0219}a", "\u{0219}i", "a\u{0219}a"])
        ->and(FrameWords::of($decomposed, $bare))->toBe(['café'])
        ->and(FrameWords::sentences('Ich heiße Anna.', $bare)[0]['written'])->toBe(['Ich', 'heiße', 'Anna']);
});

// Canon (наряд LANG-1 §1): a Spanish sentence opens with ¿ or ¡ — a mark, not a letter of its first word, and no end of
// the sentence before it. CATCHES «¿puedo» read as a word of its own and «¿» splitting a move into sentences.
it('reads a Spanish question and exclamation past their opening marks', function () {
    $es = fwPack('es', []);
    $sentences = FrameWords::sentences('¡Hola! Sí, ¿puedo pagar con tarjeta?', $es);

    expect(array_column($sentences, 'words'))->toBe([['hola'], ['sí', 'puedo', 'pagar', 'con', 'tarjeta']])
        ->and($sentences[1]['written'])->toBe(['Sí', 'puedo', 'pagar', 'con', 'tarjeta']);
});
