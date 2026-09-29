<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Assembly\NumberValues;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Language\SentenceEnds;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\Service\LineShare;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\ReplyNative;
use App\Modules\Plan\Domain\Service\WordBases;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\Service\SpokenNumbers;

/**
 * THE ROMANIAN PACK (наряд LANG-1, `config/lesson/lang/ro.php`) — Romanian is both sides of a plan: taught (ru→ro) and
 * spoken by the learner (ro→en). Every reader of the pack gets its keys in the shape it reads, and the canon is held on the
 * lines of the scouting run of the order (`docs/research/lang-1/days/ru-ro.md`, `ro-en.md`, the raw answers
 * `answers/ru-ro.json`, `answers/ro-en.json`): real sentences of the model, the learner's lines against the frames of that
 * day, the numbers it says, the translations of the role — and a few written lines where those days have none.
 */

// Canon (pack-keys §7.2): the ru→ro target side and the ro→en native side find every key they read, and no reader throws
// on a key written in the wrong shape. CATCHES a key left null or absent and a key the reader cannot read (a string for a
// list, a missing field).
it('gives every reader of the pack what it reads, in the shape it reads it', function (string $code) {
    $pack = lessonPacks()->for($code);
    $words = new LanguageWords($pack);

    expect(LanguageRoles::planTargets())->toContain($code)
        ->and(LanguageRoles::planNatives())->toContain($code);

    // The target side (ru→ro).
    foreach (['abbreviations', 'question_word_order', 'unstressed_words', 'number_words', 'number_joiners', 'irregular_forms', 'inflection_rules', 'person_swap', 'contractions', 'contractions_before', 'intro_words', 'clause_starters', 'negation', 'partitive', 'dangling_words', 'rescue_line', 'neutral_reply', 'script_letters', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the target side reads `{$key}`");
    }
    expect(NumberValues::of($pack))->not->toBeNull();
    (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
    (new FrameJudge)->breaksOff('a b', [], $pack);
    (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
    WordBases::of('abc', $pack);
    $words->isQuestion('a b');

    // The native side (ro→en).
    foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
    }
    expect($pack->talkTitleTemplate())->not->toBeNull()
        ->and((new NativeStrings($code))->talkTitle(['Recepcjonistka', 'MRI'], $pack))->toContain('MRI');
    $words->genderedPast('a b');
    $words->foreignLetters('ab');

    // No key is null: null is «not written», and the rule that needs it does not run (наряд LANG-1 §4.6); the letters are
    // the reference string of every Latin pack, character for character (the guard's neighbours).
    $written = require dirname(__DIR__, 4).'/config/lesson/lang/ro.php';
    expect(array_keys(array_filter($written, static fn (mixed $value): bool => $value === null)))->toBe([])
        ->and($written['script_letters'])->toBe('/^[\p{Latin}]$/u');
    // Each frequent word is one run of letters, lower case — the guard splits a line on everything else.
    foreach ($written['common_words'] as $word) {
        expect(preg_match('/^[\p{Ll}\p{M}]+$/u', $word))->toBe(1, "«{$word}» is one run of lower-case letters");
    }
})->with(['ro']);

// Canon CHECK-1 on Romanian (pack-keys §3.3–3.4): a sentence ends at . ? ! …, inside the Romanian quotes „…” too; the dot
// of an abbreviation ends none inside a text and closes it at its very end. Lines of the scouting days (the last ones with
// an abbreviation put into a real line). CATCHES «la dr. Lee» cut in two, «etc.» at the end read as an unclosed text, and
// a filler «dr. Popescu», «10 min.» or «10 a.m.» read as a sentence of its own (a fatal `filler.ungrammatical`).
it('ends a Romanian sentence where Romanian ends it — never at the dot of an abbreviation', function () {
    $ends = new SentenceEnds(lessonPacks()->for('ro'));

    expect($ends->count('Avem mâine la zece sau la două. Mai veniți cu actul și ajungeți cu zece minute înainte.'))->toBe(2)
        ->and($ends->terminal('Avem mâine la zece sau la două. Mai veniți cu actul și ajungeți cu zece minute înainte.'))->toBe('.')
        ->and($ends->count('Sigur. Pentru ce specialitate?'))->toBe(2)
        ->and($ends->terminalKind('Sigur. Pentru ce specialitate?'))->toBe('question')
        ->and($ends->count('Mai am nevoie și de numărul tău de telefon pentru programare.'))->toBe(1)
        ->and($ends->terminal('Recepționera spune: „Veniți cu zece minute înainte.”'))->toBe('.')
        ->and($ends->count('Ești programat azi la patru la dr. Lee.'))->toBe(1)
        ->and($ends->closesText('Ești programat azi la patru la dr. Lee.'))->toBeTrue()
        ->and($ends->count('Vă așteptăm pe str. Florilor nr. 5, la etajul doi.'))->toBe(1)
        ->and($ends->count('Te rog să aduci actul de identitate, cardul de asigurare etc.'))->toBe(1)
        ->and($ends->closesText('Te rog să aduci actul de identitate, cardul de asigurare etc.'))->toBeTrue()
        ->and($ends->count('Veniți cu 10 min. înainte. Vă așteptăm.'))->toBe(2)
        ->and($ends->carriesSentence('dr. Popescu'))->toBeFalse()
        ->and($ends->carriesSentence('10 min.'))->toBeFalse()
        ->and($ends->carriesSentence('ora 10 a.m.'))->toBeFalse()
        ->and($ends->carriesSentence('Mâine la zece.'))->toBeTrue();
});

// Canon FIX-4 §2 + LANG-1 §1 on the frames of the ru→ro scouting day (scene «Запись к врачу», the stored frames): each
// learner line of the day says its own frame and the value in its window; a negated line — «nu», and the «n» of «n-am» —
// says the same frame; a line with one word more before the window is «almost». CATCHES a negation the Romanian pack does
// not forgive («Nu mă doare gâtul» — not said), a hyphenated «N-am» read as one foreign word, an opening word («Alo»,
// «Mă scuzați») or a conjunction («și», «așa că») that opens no construction, and a judge that credits a construction
// nobody said.
it('judges the learner lines of the ru→ro day against their frames', function () {
    $frames = [];
    foreach ([
        'p1' => 'Vreau ___.', 'p2' => 'Am nevoie de ___.', 'p3' => 'Mă doare ___.', 'p4' => 'Am simptome ___.',
        'p5' => 'Pot mâine ___.', 'p6' => '___ trebuie să aduc?', 'p7' => 'Cât costă ___?',
    ] as $ref => $frame) {
        $frames[] = new ConversationPhrase('s1', $ref, $frame, '', null, null);
    }
    $ro = lessonPacks()->for('ro');
    $judge = static fn (string $heard) => (new FrameJudge)->move($heard, $frames, $ro);

    // The day's own lines — said.
    expect($judge('Vreau o programare.')->said)->toBe(['s1:p1'])
        ->and($judge('Vreau o programare.')->values)->toBe(['s1:p1' => 'o programare'])
        ->and($judge('Am nevoie de medicul de familie.')->values)->toBe(['s1:p2' => 'medicul de familie'])
        ->and($judge('Mă doare gâtul.')->values)->toBe(['s1:p3' => 'gâtul'])
        ->and($judge('Am simptome de trei zile.')->values)->toBe(['s1:p4' => 'de trei zile'])
        ->and($judge('Pot mâine la zece.')->values)->toBe(['s1:p5' => 'la zece'])
        ->and($judge('Ce documente trebuie să aduc?')->values)->toBe(['s1:p6' => 'Ce documente'])
        ->and($judge('Cât costă consultația?')->values)->toBe(['s1:p7' => 'consultația'])
        // A negated line says the same construction: «nu» anywhere, the «n» of «n-am» too.
        ->and($judge('Nu mă doare gâtul.')->values)->toBe(['s1:p3' => 'gâtul'])
        ->and($judge('N-am nevoie de medicul de familie.')->values)->toBe(['s1:p2' => 'medicul de familie'])
        ->and($judge('Nu am simptome de ieri.')->said)->toBe(['s1:p4'])
        // Opening words and a conjunction that opens a clause.
        ->and($judge('Bună ziua, vreau o programare.')->said)->toBe(['s1:p1'])
        ->and($judge('Alo, bună ziua, vreau o programare.')->values)->toBe(['s1:p1' => 'o programare'])
        ->and($judge('Mă scuzați, cât costă consultația?')->said)->toBe(['s1:p7'])
        ->and($judge('Am febră, așa că vreau o programare.')->values)->toBe(['s1:p1' => 'o programare'])
        ->and($judge('Am simptome de trei zile și mă doare gâtul.')->values)->toBe(['s1:p3' => 'gâtul', 's1:p4' => 'de trei zile'])
        // A model's cedilla «şi» is the «și» of the pack.
        ->and($judge('Am simptome de ieri şi mă doare gâtul.')->said)->toBe(['s1:p3', 's1:p4'])
        // The model's own simplified variant of B5 («Mâine la zece merge.») leaves out «pot»: almost, never said (so is
        // «Vreau ___.», whose one word before the window the move leaves out — the judge's reading, not the pack's).
        ->and($judge('Mâine la zece merge.')->said)->toBe([])
        ->and($judge('Mâine la zece merge.')->almost)->toContain('s1:p5')
        // A positive move against a negative frame is at most almost: an absent «nu» is a difference like any other.
        ->and((new FrameJudge)->move('Mă doare gâtul.', [new ConversationPhrase('x', 'p1', 'Nu mă doare ___.', '', null, null)], $ro)->almost)->toBe(['x:p1'])
        // A move that stops on a preposition or a determiner broke off — it was not misunderstood; «iar» («again») and the
        // clitic «o» close a sentence.
        ->and((new FrameJudge)->breaksOff('Am nevoie de', $frames, $ro))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Nu am niciun', [], $ro))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Am nevoie de o programare', $frames, $ro))->toBeFalse()
        ->and((new FrameJudge)->breaksOff('Mă doare iar', [], $ro))->toBeFalse()
        ->and((new FrameJudge)->breaksOff('Am văzut-o', [], $ro))->toBeFalse();

    // THE PARTITIVE: a quantity says its own «de» — «Am puțină experiență», «Nu am nicio experiență» say «Am ___ de
    // experiență»; a window of two words keeps the frame's «de».
    $experience = [new ConversationPhrase('s2', 'p1', 'Am ___ de experiență.', '', null, null)];
    expect((new FrameJudge)->move('Am puțină experiență.', $experience, $ro)->values)->toBe(['s2:p1' => 'puțină'])
        ->and((new FrameJudge)->move('Nu am nicio experiență.', $experience, $ro)->values)->toBe(['s2:p1' => 'nicio'])
        ->and((new FrameJudge)->move('Am doi ani de experiență.', $experience, $ro)->values)->toBe(['s2:p1' => 'doi ani'])
        ->and((new FrameJudge)->move('Am doi ani experiență.', $experience, $ro)->said)->toBe([]);
});

// Canon FIX-3 §4 + LANG-1 §4 on Romanian: the words of one number are one number in digits, with the pack's own lists
// (`speech()`: the canonical form of the text). Lines of the scouting days, and prices made of hundreds, tens and «și».
// CATCHES «două sute» read as «2 100», «cincisprezece» left a word, «o sută» without its article (Romanian writes no
// `articles`), a cedilla «şi» that joins nothing, «o» / «un» turned into «1» in «o programare», the dative «mie» («to
// me») read as 1000, and two numbers glued by «și» («între o sută și două sute», «între douăzeci și treizeci»).
it('reads a Romanian number said in words as the number in digits', function () {
    $speech = lessonPacks()->for('ro')->speech();
    $fold = static fn (string $text): string => implode(' ', SpokenNumbers::fold(
        explode(' ', (new LexicalNormalizer)->canonicalize($text)),
        $speech->numberWords,
        $speech->articles,
        $speech->numberJoiners,
        $speech->numberTensJoiners,
    ));

    expect($fold('Consultația costă două sute de lei.'))->toBe('consultația costă 200 de lei')
        ->and($fold('Da, te rog să vii cu cincisprezece minute mai devreme.'))->toBe('da te rog să vii cu 15 minute mai devreme')
        ->and($fold('Am simptome de trei zile.'))->toBe('am simptome de 3 zile')
        ->and($fold('Avem mâine la zece sau la două.'))->toBe('avem mâine la 10 sau la 2')
        ->and($fold('Avem azi la patru sau mâine la nouă.'))->toBe('avem azi la 4 sau mâine la 9')
        ->and($fold('Costă o sută douăzeci și cinci de lei.'))->toBe('costă 125 de lei')
        ->and($fold('Costă o sută douăzeci şi cinci de lei.'))->toBe('costă 125 de lei')
        ->and($fold('Costă o mie cinci sute de lei.'))->toBe('costă 1500 de lei')
        ->and($fold('Două mii cinci sute de lei'))->toBe('2500 de lei')
        ->and($fold('Am patruzeci și doi de ani.'))->toBe('am 42 de ani')
        ->and($fold('Costă între o sută și două sute de lei.'))->toBe('costă între 100 și 200 de lei')
        ->and($fold('Durează între douăzeci și treizeci de minute.'))->toBe('durează între 20 și 30 de minute')
        ->and($fold('Mie două bilete, vă rog.'))->toBe('mie 2 bilete vă rog')
        ->and($fold('Vreau o programare.'))->toBe('vreau o programare');
});

// Canon (LANG-1 §5, the guard of the translation) under a Romanian learner: ordinary Romanian lines of the ro→en scouting
// day (`text_native`) are translations — even the ones with one frequent Romanian word or none — and a line in a Latin
// neighbour is not. The neighbours are the deployed packs: a neighbour whose pack does not write its frequent words yet is
// told apart by nobody, and its row waits for it. CATCHES a Romanian list that holds a word ordinary in a neighbour, and
// a neighbour's list that holds a word ordinary in Romanian («de», «la», «ce», «ai», «o») — both make these lines «foreign».
it('takes an ordinary Romanian line for a translation and a neighbour\'s line for none', function () {
    $ro = lessonPacks()->for('ro');

    foreach ([
        ["I'd like to book an appointment.", 'Aș vrea să fac o programare.'],
        ['Of course. What is the visit for?', 'Sigur. Pentru ce este consultația?'],
        ['I have a sore throat.', 'Mă doare gâtul.'],
        ['How long have you had it?', 'De cât timp o ai?'],
        ['What seems to be the problem?', 'Care pare să fie problema?'],
        ['We have today at four or tomorrow at nine.', 'Avem azi la patru sau mâine la nouă.'],
        ["You're booked for today at four with Dr. Lee.", 'Ești programat azi la patru la doamna doctor Lee.'],
        ['I also need your phone number for the booking.', 'Mai am nevoie și de numărul tău de telefon pentru programare.'],
        ['Here is my phone number.', 'Iată numărul meu de telefon.'],
        ['Please bring your ID and insurance card.', 'Te rog să aduci actul de identitate și cardul de asigurare.'],
        ['I would like a checkup too.', 'Aş vrea şi un control.'],
        // Written: the words a French or an Italian list might hold and Romanian says all the time.
        ['What happened to you?', 'Ce ai pățit?'],
        ['Step by step, it is better this way.', 'Pas cu pas, e mai bine așa.'],
    ] as [$target, $native]) {
        expect(ReplyNative::missing($target, $native, $ro))->toBeFalse("«{$native}» is Romanian");
    }

    // The role's grey line said in the wrong language — the target's own, or another Latin one.
    $refused = [
        'en' => ['Ce trebuie să aduc?', 'Please bring your ID and the insurance card with you.'],
        'es' => ['Cât costă consultația?', 'La consulta cuesta doscientos lei, puede pagar aquí.'],
        'it' => ['De când vă doare gâtul?', 'Ho mal di gola da tre giorni, è molto fastidioso.'],
        'de' => ['De când vă doare gâtul?', 'Ich habe seit drei Tagen Halsschmerzen und Fieber.'],
        'pl' => ['De când vă doare gâtul?', 'Mam gorączkę i bardzo boli mnie gardło.'],
        'fr' => ['Nu înțeleg.', 'Je suis désolé, je ne comprends pas ce que vous dites.'],
    ];
    $checked = 0;
    foreach ($refused as $code => [$target, $native]) {
        if (lessonPacks()->for($code)->commonWords() === []) {
            continue;
        }
        $checked++;
        expect(ReplyNative::missing($target, $native, $ro))->toBeTrue("«{$native}» is {$code}, not Romanian");
    }
    if ($checked < 2) {
        $this->markTestIncomplete('the refusal rows wait for the Latin neighbours\' packs (en, fr, it, es): '.$checked.' of them write their common_words');
    }
});

// Canon (LANG-1 §5) the other way round: a Polish, Spanish, Italian, German, French or English learner OF ROMANIAN whose
// grey line came back in Romanian — the target's own line, not a translation — is told so by Romanian's frequent words.
// Real partner lines of the ro→en day. CATCHES a Romanian list too thin (or too shared) to tell a Romanian line from the
// learner's own language.
it('tells a Romanian line from the translation a learner of Romanian is owed', function () {
    $lines = [
        'Sigur. Pentru ce este consultația?',
        'Avem azi la patru sau mâine la nouă.',
        'Te rog să aduci actul de identitate și cardul de asigurare.',
        'Mai am nevoie și de numărul tău de telefon pentru programare.',
    ];
    $checked = 0;
    foreach (['pl', 'es', 'it', 'de', 'fr', 'en'] as $code) {
        $learners = lessonPacks()->for($code);
        if ($learners->commonWords() === []) {
            continue;
        }
        $checked++;
        foreach ($lines as $line) {
            expect(ReplyNative::missing('x', $line, $learners))->toBeTrue("«{$line}» is Romanian, not {$code}");
        }
    }
    expect($checked)->toBeGreaterThanOrEqual(2);
});

// Canon (`native.gendered_past`, a warning while the learner's gender is unknown) on Romanian: the perfect has no gender,
// what a patient says of themselves with «a fi» has — «Sunt programat/ă», «Am fost operat/ă», «Mă simt obosit/ă», «Sunt
// alergic/ă». The learner lines of the ro→en day say none. CATCHES a pattern that misses these, and one that reads the
// «sunt» of «they are», an adverb («acasă», «imediat»), a preposition («am fost la medic») or a noun («medic») as gender.
it('finds the gender a Romanian learner\'s own line says about them', function () {
    $words = new LanguageWords(lessonPacks()->for('ro'));

    foreach ([
        'Sunt programat azi la patru.' => ['programat'],
        'Sunt programată azi la patru.' => ['programată'],
        'Am fost operat anul trecut.' => ['operat'],
        'Mă simt foarte obosită.' => ['obosită'],
        'Sunt alergic la penicilină.' => ['alergic'],
        'Nu sunt sigur.' => ['sigur'],
        'Sunt răcit de trei zile.' => ['răcit'],
        'M-am simţit ameţită.' => ['ameţită'],
        'Aș fi bucuroasă.' => ['bucuroasă'],
        // Not about the learner's gender.
        'Aș vrea să fac o programare.' => [],
        'Mă doare gâtul.' => [],
        'O am de trei zile.' => [],
        'Azi la patru este bine pentru mine.' => [],
        'Bine, ne vedem azi la patru.' => [],
        'Iată numărul meu de telefon.' => [],
        'Sunt acasă.' => [],
        'Sunt medic.' => [],
        'Am fost la medic ieri.' => [],
        'Analizele sunt programate.' => [],
        'Sunt bine, mulțumesc.' => [],
        'Sunt de acord.' => [],
        'Sunt imediat acolo.' => [],
        'Sunt două analize.' => [],
        'Mă simt mai bine.' => [],
    ] as $line => $forms) {
        expect($words->genderedPast($line))->toBe($forms, "«{$line}»");
    }
});

// Canon («Поймай число» and READINGS on the learner's side), on the lines of the ro→en day: the value of a line is the
// amount as the line says it, with its preposition; a reading in Latin letters — an IPA «ə» among them — has no letter
// of another writing. CATCHES an option «Cincisprezece minute» without its «cu» or «Cu 10» without its «min», and a Latin
// reading read as foreign.
it('reads the native side of a ro→en day', function () {
    $ro = lessonPacks()->for('ro');
    $words = new LanguageWords($ro);

    $values = NumberValues::of($ro);
    expect($values?->value('Da, te rog să vii cu cincisprezece minute mai devreme.'))->toBe(['text' => 'Cu cincisprezece minute', 'number' => true])
        ->and($values?->value('Ești programat azi la patru la doamna doctor Lee.'))->toBe(['text' => 'La patru', 'number' => true])
        ->and($values?->value('Veniți cu 10 min. înainte.'))->toBe(['text' => 'Cu 10 min', 'number' => true])
        ->and($values?->value('Te rog să aduci actul de identitate și cardul de asigurare.'))->toBeNull()
        ->and($words->foreignLetters('ai hv ə sor throuăt'))->toBe([]);
});

// Canon (LANG-1 §6, `talk_title_template`): a Romanian learner's talk names its roles in no case, lower-cased but an
// acronym, joined by «și»; no role at all — «Conversație». CATCHES the English fallback («Talk to the recepționer and the
// medic») and an acronym lower-cased («oRL»).
it('titles a talk for a Romanian learner', function () {
    $ro = lessonPacks()->for('ro');
    $strings = new NativeStrings('ro');

    expect($strings->talkTitle(['Recepționer', 'Medic'], $ro))->toBe('Conversație: recepționer și medic')
        ->and($strings->talkTitle(['Recepționer', 'ORL', 'Medic'], $ro))->toBe('Conversație: recepționer, ORL și medic')
        ->and($strings->talkTitle([], $ro))->toBe('Conversație');
});
