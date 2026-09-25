<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\Language\SentenceEnds;

/**
 * WHERE A SENTENCE ENDS (наряд CHECK-1, docs/plan-v2.md §4): one rule for «does the text end with an end mark» and «how
 * many sentences», read off the pack's `sentence_ends` and `abbreviations`. The words are the deployment's own packs.
 */
function endsOf(string $code): SentenceEnds
{
    return new SentenceEnds(lessonPacks()->for($code));
}

/** The English pack with the abbreviations key taken out — a pack nobody wrote the list for. */
function endsWithoutList(): SentenceEnds
{
    $en = require dirname(__DIR__, 3).'/config/lesson/lang/en.php';
    unset($en['abbreviations']);

    return new SentenceEnds((new LanguagePacks(['en' => $en]))->for('en'));
}

// Canon CHECK-1 (вердикт архитектора): «closesText — текст закончен знаком конца? Точка после сокращения в самом КОНЦЕ
// текста закрывает его («Come at 3 p.m.» — закончен); внутри текста предложение не кончает.» Catches a rule that reads
// a closed frame as unmarked, and one that stops reading a real full stop, a question mark or an exclamation mark.
it('closes a text with a full stop, a question mark or an exclamation mark — an abbreviation\'s dot at the very end too', function () {
    $en = endsOf('en');

    expect($en->terminal('See you tomorrow.'))->toBe('.')
        ->and($en->terminal('Yes?'))->toBe('?')
        ->and($en->terminal('Great!'))->toBe('!')
        ->and($en->terminal('Really?!'))->toBe('!')
        ->and($en->terminal('He said "no."'))->toBe('.')
        ->and($en->terminalKind('Yes?'))->toBe('question')
        ->and($en->terminal('Come at 3 p.m.'))->toBe('.')
        ->and($en->closesText('Come at 3 p.m.'))->toBeTrue()
        ->and($en->terminalKind('Come at 3 p.m.'))->toBe('statement')
        // The list is read whatever the case: «3 P.M.» is the time too.
        ->and($en->count('Ask DR. SMITH. He knows.'))->toBe(2)
        ->and($en->terminal('Dr. Smith'))->toBe('')
        ->and($en->terminal('Come at 3 p.m..'))->toBe('.')
        ->and($en->terminal('at 3 p.m.?'))->toBe('?')
        ->and($en->terminal('Hello'))->toBe('')
        ->and($en->closesText('Hello'))->toBeFalse()
        ->and($en->terminal(''))->toBe('');
});

// Canon CHECK-1 (вердикт архитектора): «carriesSentence — наполнение несёт собственное предложение? Точка сокращения —
// нет («3 p.m.», «Dr. Smith»); «See you tomorrow.», «Yes?» — да.» Catches the live day's reading of «3 p.m.» as a
// sentence with its own full stop, and a rule that lets a real sentence into the slot.
it('reads a fragment as carrying a sentence of its own by a real end mark, never by an abbreviation\'s dot', function () {
    $en = endsOf('en');

    expect($en->carriesSentence('See you tomorrow.'))->toBeTrue()
        ->and($en->carriesSentence('Yes?'))->toBeTrue()
        ->and($en->carriesSentence('Great!'))->toBeTrue()
        ->and($en->carriesSentence('3 p.m.'))->toBeFalse()
        ->and($en->carriesSentence('5:30 p.m.'))->toBeFalse()
        ->and($en->carriesSentence('3 P.M.'))->toBeFalse()
        ->and($en->carriesSentence('Dr. Smith'))->toBeFalse()
        ->and($en->carriesSentence('e.g.'))->toBeFalse()
        ->and($en->carriesSentence('at home'))->toBeFalse()
        // Of a run of marks only the ones that are no abbreviation's count: the assembly's «Come at 3 p.m..».
        ->and($en->carriesSentence('at 3 p.m..'))->toBeTrue()
        ->and($en->carriesSentence('at 3 p.m.?'))->toBeTrue()
        ->and($en->carriesSentence(''))->toBeFalse();
});

// Canon CHECK-1: «We have 3 p.m. and 5:30 p.m. today.» — одно предложение; «I'm here. Are you?» — два. Catches the live
// day's count of three sentences in one, a dot inside a number read as an end, and two sentences read as one.
it('counts sentences by their ends, an abbreviation\'s dot and a dot inside a number ending none', function () {
    $en = endsOf('en');

    expect($en->count('We have 3 p.m. and 5:30 p.m. today.'))->toBe(1)
        ->and($en->count("I'm here. Are you?"))->toBe(2)
        ->and($en->count('Take 3.5 mg. Then rest.'))->toBe(2)
        ->and($en->count('Ask Dr. Smith. He knows.'))->toBe(2)
        // «No.» is not listed: it is the answer as often as the number, and the answer ends a sentence.
        ->and($en->count('Oh no. It hurts.'))->toBe(2)
        ->and($en->count('Hello'))->toBe(1)
        ->and($en->count('...'))->toBe(0)
        ->and($en->count(''))->toBe(0)
        ->and($en->sentences('Ask Dr. Smith. He knows.'))->toBe(['Ask Dr. Smith', 'He knows'])
        ->and($en->questionMarks('Why? Why not?'))->toBe(2);
});

// Canon CHECK-1: «пакет без ключа — список пустой, каждая точка — конец». Catches a rule that keeps a list of its own
// when the pack has none, and one that throws for a pack without the key.
it('reads every dot as an end for a pack that lists no abbreviations', function () {
    $bare = endsWithoutList();

    expect($bare->carriesSentence('3 p.m.'))->toBeTrue()
        ->and($bare->count('We have 3 p.m. and 5:30 p.m. today.'))->toBe(3)
        ->and($bare->terminal('Yes?'))->toBe('?');
});

// Canon CHECK-1: «ru — т. е., т. д., т. п., г., ул.» — the native side is asked whether a frame ends with a mark. Catches
// a Russian list that is not read, and one that reads an abbreviation with a space inside as two words.
it('reads the Russian abbreviations, a space inside one being any run of spaces', function () {
    $ru = endsOf('ru');

    expect($ru->carriesSentence('на ул.'))->toBeFalse()
        ->and($ru->terminal('Я живу на ул.'))->toBe('.')
        ->and($ru->terminal('Я живу на улице.'))->toBe('.')
        ->and($ru->count('Т. е. так. Или нет.'))->toBe(2)
        ->and($ru->count('Т.  е. так.'))->toBe(1)
        ->and($ru->terminal('Ему нужен врач?'))->toBe('?');
});

// A pack with the key but no sentence_ends is not a language the rule can read: the caller asks the context first, and
// the rule itself throws rather than guess (the packs' contract, GEN-2b).
it('throws for a pack without sentence_ends', function () {
    expect(static fn () => new SentenceEnds(LanguagePack::none('xx')))->toThrow(App\Modules\Plan\Domain\Exception\LanguagePackKeyMissing::class);
});

/**
 * A pack of the four marks every language writes and the abbreviations given — a language of LANG-1 as its executor
 * writes it (наряд LANG-1 §1); `$extra` marks over them (a pack that lists the Spanish opening ones by mistake).
 *
 * @param  list<string>  $abbreviations
 * @param  array<string, string>  $extra
 */
function endsOfMarks(array $abbreviations = [], array $extra = []): SentenceEnds
{
    return new SentenceEnds(new LanguagePack('xx', [
        'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis', ...$extra],
        'abbreviations' => $abbreviations,
    ]));
}

// Canon (наряд LANG-1 §1): «немецкие кавычки „…“ (U+201E/U+201C) и французские « … » с пробелами внутри — закрывающие там,
// где может стоять конец предложения». CATCHES a German quotation whose full stop stands before «“» read as no end (the
// frame «„Ich komme.“» with no mark, two sentences counted as one), a French one with the spaces of its typography — a
// no-break one too — read as unclosed, and an abbreviation right after an opening quote not read as one.
it('closes a sentence before the German and the French closing quotes', function () {
    $de = endsOfMarks(['z. B.', 'Dr.']);
    $fr = endsOfMarks(['M.', 'etc.']);

    expect($de->terminal('„Ich komme.“'))->toBe('.')
        ->and($de->carriesSentence('„Ja.“'))->toBeTrue()
        ->and($de->count('Er sagte: „Ich komme.“ Dann ging er.'))->toBe(2)
        ->and($de->terminalKind('Sie fragte: „Kommst du?“'))->toBe('question')
        ->and($de->terminal('‚Ja.‘'))->toBe('.')
        ->and($de->terminal('»Ich komme.«'))->toBe('.')
        ->and($de->count('„Dr. Müller kommt.“'))->toBe(1)
        ->and($de->count('Obst, z. B. Äpfel, ist gesund.'))->toBe(1)
        ->and($fr->terminal('« Oui. »'))->toBe('.')
        ->and($fr->terminalKind("«\u{00A0}Vous avez de la fièvre\u{00A0}?\u{00A0}»"))->toBe('question')
        ->and($fr->terminalKind('Vous avez de la fièvre ?'))->toBe('question')
        ->and($fr->count('« Oui. » Il part.'))->toBe(2)
        ->and($fr->count("Il a dit « non ». C'est tout."))->toBe(2)
        ->and($fr->count('« M. Dupont arrive. »'))->toBe(1)
        // The Polish and Romanian „…” close as the English ones did.
        ->and($de->terminal('„Tak.”'))->toBe('.');
});

// Canon (наряд LANG-1 §1): «испанские открывающие ¿ ¡ ничего не ломают». CATCHES a question counted twice, a sentence
// ended before its own ¿, and an abbreviation after ¿ read as an end — and a pack that lists ¿ ¡ among its ends by mistake
// doing any of it.
it('reads the Spanish opening marks as no end, whatever the pack lists', function () {
    foreach ([endsOfMarks(['Sr.', 'Sra.']), endsOfMarks(['Sr.', 'Sra.'], ['¿' => 'question', '¡' => 'exclamation'])] as $es) {
        expect($es->questionMarks('¿Puedo pagar con tarjeta?'))->toBe(1)
            ->and($es->terminalKind('¿Puedo pagar con tarjeta?'))->toBe('question')
            ->and($es->terminalKind('¡Qué bien!'))->toBe('exclamation')
            ->and($es->count('¿Puedo pagar? ¡Claro!'))->toBe(2)
            ->and($es->sentences('Sí, ¿puedo pagar con tarjeta? ¡Claro!'))->toBe(['Sí, ¿puedo pagar con tarjeta', '¡Claro'])
            ->and($es->count('¿El Sr. García está?'))->toBe(1)
            ->and($es->count('¿Sr. García?'))->toBe(1)
            ->and($es->carriesSentence('¿Sí?'))->toBeTrue()
            ->and($es->carriesSentence('Sr.'))->toBeFalse()
            ->and($es->terminal('¿'))->toBe('');
    }
});

// The key spec (наряд LANG-1 §1): `sentence_ends` is never written empty — every language ends a sentence with a full stop.
// A pack that writes it so anyway knows no end rather than breaking every check that reads it. CATCHES an empty mark class
// compiled into a pattern that is no pattern.
it('knows no end for a pack whose sentence_ends is empty, and does not break', function () {
    $none = new SentenceEnds(new LanguagePack('xx', ['sentence_ends' => []]));

    expect($none->count('Hola. Adiós.'))->toBe(1)
        ->and($none->terminal('Hola.'))->toBe('')
        ->and($none->sentences('Hola. Adiós.'))->toBe(['Hola. Adiós.'])
        ->and($none->questionMarks('¿Qué?'))->toBe(0);
});
