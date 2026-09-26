<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\NumberValues;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Language\PackSkip;
use App\Modules\Plan\Domain\Check\Language\SentenceEnds;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\Service\LineShare;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\ReplyNative;
use App\Modules\Plan\Domain\Service\WordBases;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\Service\SpokenNumbers;

/**
 * THE POLISH PACK (наряд LANG-1, `config/lesson/lang/pl.php`) — Polish is both sides of a plan: taught (ru→pl) and spoken
 * by the learner (pl→en). Every reader of the pack gets its keys in the shape it reads, and the canon is held on the lines
 * of the scouting run of the order (`docs/research/lang-1/days/ru-pl.md`, `pl-en.md`): real sentences of the model, the
 * learner's lines against the frames of that day, the numbers it says, the translations of the role.
 */

// Canon (pack-keys §7.2): no check of the ru→pl target side and none of the pl→en native side is skipped for a key the
// pack does not write, and no reader throws on a key written in the wrong shape. CATCHES a key left null or absent
// (`lang.pack_missing` on every day of the pair) and a key the reader cannot read (a string for a list, a missing field).
it('gives every reader of the pack what it reads, in the shape it reads it', function (string $code) {
    $pack = lessonPacks()->for($code);
    $words = new LanguageWords($pack);
    $gaps = static function (string $side) use ($code): array {
        $context = $side === 'target' ? lessonContext('ru', $code) : lessonContext($code, 'en');
        $request = new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays);
        (new LessonValidator)->run((new LessonParser)->parse(FakePlanModel::lessonPayload($request)), $context);

        return array_values(array_filter(
            array_map(static fn (PackSkip $skip): array => $skip->toArray(), $context->skips->all()),
            static fn (array $skip): bool => $skip['language'] === $code,
        ));
    };

    expect(LanguageRoles::planTargets())->toContain($code)
        ->and(LanguageRoles::planNatives())->toContain($code);

    // The target side (ru→pl).
    expect($gaps('target'))->toBe([]);
    foreach (['abbreviations', 'question_word_order', 'unstressed_words', 'number_words', 'number_joiners', 'irregular_forms', 'inflection_rules', 'person_swap', 'contractions', 'contractions_before', 'intro_words', 'clause_starters', 'negation', 'partitive', 'dangling_words', 'rescue_line', 'neutral_reply', 'script_letters', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the target side reads `{$key}`");
    }
    expect(NumberValues::of($pack))->not->toBeNull();
    (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
    (new FrameJudge)->breaksOff('a b', [], $pack);
    (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
    WordBases::of('abc', $pack);
    $words->isQuestion('a b');
    $words->asksTwice('a, b?');
    $words->isCloser('a');
    $words->clause('a b c');
    $words->articleMismatch('a', 'b');
    $words->unresolvedPronoun('a b ___.');
    $words->valueKind('a 2');

    // The native side (pl→en).
    expect($gaps('native'))->toBe([]);
    foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
    }
    expect($pack->talkTitleTemplate())->not->toBeNull()
        ->and((new NativeStrings($code))->talkTitle(['Recepcjonistka', 'MRI'], $pack))->toContain('MRI');
    $words->agreeingWithSlot('a b ___ c d.');
    $words->genderedPast('a b');
    $words->foreignLetters('ab');
    $words->readsInScript('ab');
    $words->valueKind('a 2');

    // No key is null: null is «not written» and counts `lang.pack_missing` (наряд LANG-1 §4.6).
    $written = require dirname(__DIR__, 4).'/config/lesson/lang/pl.php';
    expect(array_keys(array_filter($written, static fn (mixed $value): bool => $value === null)))->toBe([]);
})->with(['pl']);

// Canon CHECK-1 on Polish (pack-keys §3.3–3.4): a sentence ends at . ? ! …, inside the Polish quotes „…” too; the dot of
// an abbreviation ends none inside a text and closes it at its very end. Lines of the scouting days (the ones with an
// abbreviation are real lines with one put in). CATCHES «o godz. 10:00» cut in two, «itp.» at the end read as an unclosed
// text, a filler «godz. 15:00» read as a sentence of its own (a fatal `filler.ungrammatical`) — and «OK.» taken for the
// abbreviation of «około», which would glue a reply to the sentence after it.
it('ends a Polish sentence where Polish ends it — never at the dot of an abbreviation', function () {
    $ends = new SentenceEnds(lessonPacks()->for('pl'));

    expect($ends->count('Dobrze. Jaki jest powód wizyty?'))->toBe(2)
        ->and($ends->terminalKind('Dobrze. Jaki jest powód wizyty?'))->toBe('question')
        ->and($ends->count('Proszę przynieść dokument i kartę ubezpieczenia.'))->toBe(1)
        ->and($ends->terminal('Proszę przynieść dokument i kartę ubezpieczenia.'))->toBe('.')
        ->and($ends->count('Oczywiście. Proszę powiedzieć, co się dzieje.'))->toBe(2)
        ->and($ends->count('Tak. To Green Street 12, obok apteki.'))->toBe(2)
        ->and($ends->terminal('Recepcjonistka mówi: „Proszę przyjść dziesięć minut wcześniej.”'))->toBe('.')
        // The abbreviations of the pack, put into real lines of the days.
        ->and($ends->count('Mamy wolny termin jutro o godz. 10:00.'))->toBe(1)
        ->and($ends->count('Tak. To ul. Zielona 12, obok apteki.'))->toBe(2)
        ->and($ends->count('Proszę przynieść dokument, np. dowód osobisty, i kartę ubezpieczenia.'))->toBe(1)
        ->and($ends->count('Proszę przyjść dziesięć min. wcześniej.'))->toBe(1)
        ->and($ends->count('Proszę wziąć dowód, kartę ubezpieczenia itp.'))->toBe(1)
        ->and($ends->closesText('Proszę wziąć dowód, kartę ubezpieczenia itp.'))->toBeTrue()
        ->and($ends->carriesSentence('godz. 15:00'))->toBeFalse()
        ->and($ends->carriesSentence('Jutro o dziesiątej.'))->toBeTrue()
        // The genitive «doktora» takes the dot («u dr. Nowaka»), a room and a floor too — a filler that ends on one of
        // them carries no sentence of its own (a fatal `filler.ungrammatical` otherwise).
        ->and($ends->count('Jesteś zapisany na dziś na czwartą do dr. Lee.'))->toBe(1)
        ->and($ends->carriesSentence('pok. 12, II p.'))->toBeFalse()
        ->and($ends->carriesSentence('2 szt.'))->toBeFalse()
        // «OK.» is a reply, not «około»; «im» is a pronoun: each ends its sentence.
        ->and($ends->count('Ok. Przyjdę dziesięć minut wcześniej.'))->toBe(2)
        ->and($ends->count('Proszę to powiedzieć im. Dziękuję.'))->toBe(2);
});

// Canon (partner.two_questions) on Polish: one sentence that opens with a question word and asks again after «i / a /
// oraz / albo / lub» and another one — a comma before it or not, Polish puts none before «i». Partner lines of the ru→pl
// day and lines of the talk. CATCHES the English «, and …» rule read into Polish, where «Rozumiem, a od kiedy?» — assent
// and ONE question — is how every partner asks, and a second question joined by «i» with no comma, which that rule missed.
it('reads two questions in one Polish sentence, and one question after a word of assent as one', function () {
    $words = new LanguageWords(lessonPacks()->for('pl'));

    foreach (['Dobrze. Jaki jest powód wizyty?', 'Tak, mamy jutro o piętnastej.', 'Proszę przynieść dokument i kartę ubezpieczenia.',
        'Rozumiem, a od kiedy ma pan gorączkę?', 'Dobrze, a jak się pan nazywa?', 'Czy woli pan rano, czy po południu?',
        'Czy jest coś po południu albo jutro rano?', 'Czy ma pan gorączkę, a może kaszel?', 'Czy to coś pilnego na dziś?'] as $one) {
        expect($words->asksTwice($one))->toBeFalse("«{$one}» asks once");
    }
    foreach (['Czy ma pan gorączkę i czy boli gardło?', 'Od kiedy to trwa i czy ma pan gorączkę?',
        'Kiedy to się zaczęło, a jak się pan czuje teraz?', 'Jak się pan nazywa i jaki jest pana numer telefonu?',
        'Dobrze, czy ma pan skierowanie i czy ma pan dowód?', 'Czy ma pan gorączkę? A czy boli gardło?'] as $two) {
        expect($words->asksTwice($two))->toBeTrue("«{$two}» asks twice");
    }
});

// Canon FIX-4 §2 + LANG-1 §1 on the frames of the ru→pl scouting day (scene «Запись к врачу», p1–p6): each learner line of
// the day says its own frame and the value in its window; a negated line — «nie» anywhere, before the verb inside the
// frame's words or at the very start — says the same frame; the model's own simplified variant of B1, one word short, is
// «almost». CATCHES a negation the Polish pack does not forgive («Nie mam gorączki» — not said), an opening word that does
// not let the frame start after it («Dobrze, przyjdę…», «Dobrze, to przyjdę…», «Proszę pani, chcę…»), a conjunction that
// opens no clause («…, ale czy jest coś…», «…, ponieważ mam…») or one that opens a clause of no frame («…, kiedy mam
// przyjść» is no «Mam ___»), a move cut off on a preposition or on «a» read as misunderstood, and a judge that credits a
// construction nobody said.
it('judges the learner lines of the ru→pl day against their frames', function () {
    $frames = [];
    foreach ([
        'p1' => 'Chcę umówić wizytę ___.', 'p2' => 'Mam ___.', 'p3' => '___ mi pasuje.', 'p4' => 'Czy jest coś ___?',
        'p5' => 'Czy mam przynieść ___?', 'p6' => 'Przyjdę ___.',
    ] as $ref => $frame) {
        $frames[] = new ConversationPhrase('s1', $ref, $frame, '', null, null);
    }
    $pl = lessonPacks()->for('pl');
    $judge = static fn (string $heard) => (new FrameJudge)->move($heard, $frames, $pl);

    // The day's own lines (B1–B8) — said, each with its value.
    expect($judge('Chcę umówić wizytę do lekarza.')->values)->toBe(['s1:p1' => 'do lekarza'])
        ->and($judge('Mam ból gardła i gorączkę.')->values)->toBe(['s1:p2' => 'ból gardła i gorączkę'])
        ->and($judge('Jutro o dziesiątej mi pasuje.')->values)->toBe(['s1:p3' => 'Jutro o dziesiątej'])
        ->and($judge('Czy jest coś po południu?')->values)->toBe(['s1:p4' => 'po południu'])
        ->and($judge('Czy mam przynieść dokument?')->values)->toBe(['s1:p5' => 'dokument'])
        ->and($judge('Dobrze, przyjdę dziesięć minut wcześniej.')->values)->toBe(['s1:p6' => 'dziesięć minut wcześniej'])
        // Said in the negative: «nie» is free anywhere — at the start, and inside the frame's words.
        ->and($judge('Nie mam gorączki.')->values)->toBe(['s1:p2' => 'gorączki'])
        ->and($judge('Jutro o dziesiątej mi nie pasuje.')->values)->toBe(['s1:p3' => 'Jutro o dziesiątej'])
        // A conjunction opens a clause: the frame starts after «ale», «że», «ponieważ».
        ->and($judge('Nie mogę jutro, ale czy jest coś po południu?')->values)->toBe(['s1:p4' => 'po południu'])
        ->and($judge('Dzwonię, ponieważ mam gorączkę.')->values)->toBe(['s1:p2' => 'gorączkę'])
        // The openers of a Polish move: «to» («then»), a polite address, «niestety», a hesitation.
        ->and($judge('Dobrze, to przyjdę dziesięć minut wcześniej.')->values)->toBe(['s1:p6' => 'dziesięć minut wcześniej'])
        ->and($judge('Proszę pani, chcę umówić wizytę do lekarza.')->values)->toBe(['s1:p1' => 'do lekarza'])
        ->and($judge('Niestety nie mam dokumentu.')->values)->toBe(['s1:p2' => 'dokumentu'])
        ->and($judge('Em, mam kaszel.')->values)->toBe(['s1:p2' => 'kaszel'])
        // …and a subordinate «kiedy» is no clause of a frame: «Nie wiem, kiedy mam przyjść» says no «Mam ___».
        ->and($judge('Nie wiem, kiedy mam przyjść.')->said)->toBe([])
        // The model's own simplified variant of B1 («Chcę wizytę do lekarza.») leaves «umówić» out: almost, never said.
        ->and($judge('Chcę wizytę do lekarza.')->said)->toBe([])
        ->and($judge('Chcę wizytę do lekarza.')->almost)->toContain('s1:p1')
        // A positive move against a negative frame is at most almost: an absent «nie» is a difference like any other.
        ->and((new FrameJudge)->move('Mam gorączkę.', [new ConversationPhrase('x', 'p1', 'Nie mam ___.', '', null, null)], $pl)->almost)->toBe(['x:p1'])
        // A move that stops on a preposition broke off — it was not misunderstood.
        ->and((new FrameJudge)->breaksOff('Chcę umówić wizytę do', $frames, $pl))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Mam ból gardła a', $frames, $pl))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Chcę umówić wizytę do lekarza', $frames, $pl))->toBeFalse()
        ->and((new FrameJudge)->breaksOff('To jest moje', $frames, $pl))->toBeFalse();
});

// Canon FIX-3 §4 + LANG-1 §4 on Polish: the words of one number are one number in digits, with the pack's own lists
// (`speech()`: the canonical form of the text), and Polish joins them with no word. Lines of the scouting days, a price of
// a hundred, tens and units, a year. CATCHES «sto dwadzieścia pięć» read as «100 20 5», «dwa tysiące» as «2 1000», a feminine
// «dwie» left a word — and an inflected «trzech» or a clock ordinal «dziesiątej» turned into a digit on one side only.
it('reads a Polish number said in words as the number in digits', function () {
    $speech = lessonPacks()->for('pl')->speech();
    $fold = static fn (string $text): string => implode(' ', SpokenNumbers::fold(
        explode(' ', (new LexicalNormalizer)->canonicalize($text)),
        $speech->numberWords,
        $speech->articles,
        $speech->numberJoiners,
        $speech->numberTensJoiners,
    ));

    expect($fold('Proszę przyjść dziesięć minut wcześniej.'))->toBe('proszę przyjść 10 minut wcześniej')
        ->and($fold('Piętnaście minut wcześniej'))->toBe('15 minut wcześniej')
        ->and($fold('Tak. To Green Street dwanaście, obok apteki.'))->toBe('tak to green street 12 obok apteki')
        ->and($fold('Wizyta kosztuje sto dwadzieścia pięć złotych.'))->toBe('wizyta kosztuje 125 złotych')
        ->and($fold('Dwa tysiące dwadzieścia sześć'))->toBe('2026')
        ->and($fold('Poproszę dwie tabletki.'))->toBe('poproszę 2 tabletki')
        ->and($fold('dwadzieścia jeden'))->toBe('21')
        ->and($fold('jeden dwa trzy'))->toBe('1 2 3')
        ->and($fold('To trwa od trzech dni.'))->toBe('to trwa od trzech dni')
        ->and($fold('Jutro o dziesiątej mi pasuje.'))->toBe('jutro o dziesiątej mi pasuje');
});

// Canon (LANG-1 §5, the guard of the translation) under a Polish learner: ordinary Polish lines of the pl→en scouting day
// (`text_native` of the receptionist) are translations — even those with one frequent Polish word or none — and a line in
// a Latin neighbour is not. The neighbours are the deployed packs, whatever they come to write. CATCHES a Polish list that
// holds a word ordinary in a neighbour, and a neighbour's list that holds a word ordinary in Polish («to», «i», «na», «do»,
// «o», «nie», «ale» — all in these lines): both make an honest Polish line «foreign».
it('takes an ordinary Polish line for a translation and a neighbour\'s line for none', function () {
    $pl = lessonPacks()->for('pl');

    foreach ([
        ['Of course. Please tell me the problem.', 'Oczywiście. Proszę powiedzieć, co się dzieje.'],
        ['Is it something urgent today?', 'Czy to coś pilnego na dziś?'],
        ['We have today at four or tomorrow at nine.', 'Mamy dziś o czwartej albo jutro o dziewiątej.'],
        ['Yes, that time is still free.', 'Tak, ten termin jest jeszcze wolny.'],
        ['I need your full name for the booking.', 'Potrzebuję twojego imienia i nazwiska do rezerwacji.'],
        ['Please bring your ID and arrive ten minutes early.', 'Proszę wziąć dokument tożsamości i przyjść dziesięć minut wcześniej.'],
        ["Yes. It's 12 Green Street, near the pharmacy.", 'Tak. To Green Street 12, obok apteki.'],
        ["You're booked for today at four with Dr. Lee.", 'Jesteś zapisany na dziś na czwartą do doktor Lee.'],
        ['—', 'Nie wiem, czy to jest „The Doctor Is In”, ale tak mówi recepcja.'],
        // Short lines with no frequent word or one, a Polish word other languages also know («pas», «je», «moi»), a
        // quoted English button.
        ['—', 'Dziękuję, do widzenia!'],
        ['—', 'Proszę je zapiąć, to jest pas.'],
        ['—', 'Moi rodzice przyjdą jutro.'],
        ['—', 'Proszę kliknąć „Book it now” na stronie.'],
    ] as [$target, $native]) {
        expect(ReplyNative::missing($target, $native, $pl))->toBeFalse("«{$native}» is Polish");
    }

    // The Polish list reads no ordinary line of another Latin learner as Polish (its «nie», «z», «od», «dal» aside).
    foreach (['de' => 'Ich war noch nie hier, z. B. am Montag.', 'it' => 'Ci vediamo dal medico da oggi.',
        'ro' => 'Te rog, vino la ora zece.', 'es' => 'Vale, a las diez.'] as $code => $line) {
        expect(ReplyNative::missing('—', $line, lessonPacks()->for($code)))->toBeFalse("«{$line}» is {$code}");
    }

    // An English line where the Polish should be (the mini model's slip), and a Romanian one: not a translation.
    expect(ReplyNative::missing('—', 'Of course. Please tell me the problem.', $pl))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Please bring your ID and arrive ten minutes early.', $pl))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Vă rog să aduceți actul de identitate și cardul de asigurare.', $pl))->toBeTrue();
});

// The learner's own language (pl→en): the readings, the native frames of the day and what a native line says of the
// learner's gender. CATCHES a strict `script_letters` (a Polish reading with a «v» failing a day), a Georgian «იან» let
// through, an agreement read off every «-a» of a verb («To trwa ___», «Do zobaczenia ___»), off «temu» («ago»), «ci» («to
// you») or «dlaczego», a gendered past missed or read off a noun («z gardłem» on the sore-throat day, «z Michałem»), and a
// Polish time word («przed południem», «dziennie», «godz.») read as no time.
it('reads a Polish learner\'s readings, native frames and lines', function () {
    $words = new LanguageWords(lessonPacks()->for('pl'));

    expect($words->foreignLetters('ajd lajk tu mejk en apojntment'))->toBe([])
        ->and($words->foreignLetters('wizit do doktora'))->toBe([])
        ->and($words->foreignLetters('maj nejm iz იან kowalski'))->toBe(['ი', 'ა', 'ნ'])
        ->and($words->readsInScript('okej, ajl bring maj aj-di'))->toBeTrue()
        ->and($words->readsInScript('ken aj haww ___'))->toBeTrue()
        ->and($words->readsInScript('ai hv ə sor trołt'))->toBeFalse()
        // The native frames of the pl→en day agree with nothing; an adjective or a determiner before the slot does.
        ->and(array_merge(...array_map($words->agreeingWithSlot(...), [
            'Chcę umówić ___', 'Mam ___', 'To trwa ___', 'Czy mogę przyjść ___', 'Nazywam się ___', 'Wezmę ___',
            'Czy mogę dostać ___', 'Do zobaczenia ___', 'Czy to ___?',
        ])))->toBe([])
        ->and($words->agreeingWithSlot('Mam wysoką ___.'))->toBe(['wysoką'])
        ->and($words->agreeingWithSlot('Czy jest wolny ___?'))->toBe(['wolny'])
        ->and($words->agreeingWithSlot('___ jest wolny?'))->toBe(['wolny'])
        ->and($words->agreeingWithSlot('Jaki ___ pan woli?'))->toBe(['jaki'])
        ->and($words->agreeingWithSlot('Szukam dobrego ___.'))->toBe(['dobrego'])
        ->and($words->agreeingWithSlot('Mam kilka wysokich ___.'))->toBe(['wysokich'])
        // «ago», «to you», «why» agree with nothing.
        ->and($words->agreeingWithSlot('Zaczęło się ___ temu.'))->toBe([])
        ->and($words->agreeingWithSlot('Czy mogę ci ___?'))->toBe([])
        ->and($words->agreeingWithSlot('Nie wiem, dlaczego ___.'))->toBe([])
        // The learner's gender in a line of their own — never the instrumental of a noun, the throat of the day first.
        ->and($words->genderedPast('Chcę umówić wizytę.'))->toBe([])
        ->and($words->genderedPast('Byłam u lekarza wczoraj.'))->toBe(['byłam'])
        ->and($words->genderedPast('Chciałbym umówić wizytę.'))->toBe(['chciałbym'])
        ->and($words->genderedPast('Chciałem zapytać o wyniki.'))->toBe(['chciałem'])
        ->and($words->genderedPast('Leży pod stołem.'))->toBe([])
        ->and($words->genderedPast('Mam problem z gardłem.'))->toBe([])
        ->and($words->genderedPast('Rozmawiałem z Michałem z oddziałem ratunkowym.'))->toBe(['rozmawiałem'])
        // Time words of a Polish option: a part of the day in its case, a dose, an hour abbreviated — «number or time».
        ->and($words->valueKind('przed południem'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->valueKind('trzy razy dziennie'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->valueKind('o godz. 15'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->isTime('zimny'))->toBeFalse()
        ->and($words->isNumber('jednak'))->toBeFalse();
});

// Canon (LANG-1 §6, `talk_title_template`): a Polish learner's talk names its roles in no case, lower-cased but an
// acronym, joined by «i»; a role two scenes share is said once; no role at all — «Rozmowa». CATCHES the English fallback
// («Talk to the recepcjonistka and the lekarz») and an acronym lower-cased («mRI»).
it('titles a talk for a Polish learner', function () {
    $pl = lessonPacks()->for('pl');
    $strings = new NativeStrings('pl');

    expect($strings->talkTitle(['Recepcjonistka', 'Lekarz'], $pl))->toBe('Rozmowa: recepcjonistka i lekarz')
        ->and($strings->talkTitle(['Recepcjonistka', 'MRI', 'Lekarz'], $pl))->toBe('Rozmowa: recepcjonistka, MRI i lekarz')
        ->and($strings->talkTitle(['Lekarz', 'lekarz'], $pl))->toBe('Rozmowa: lekarz')
        ->and($strings->talkTitle([], $pl))->toBe('Rozmowa');
});
