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
use App\Modules\Plan\Domain\ValueObject\MoveVerdict;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\Service\SpokenNumbers;

/**
 * THE GERMAN PACK (наряд LANG-1, исполнитель de) — German is both sides of the plan: the TARGET of ru → de (the live plan of
 * part D) and the LEARNER'S OWN language of de → en. The lines are the scouting run's (`docs/research/lang-1/days/ru-de.md`
 * for German said and heard, `de-en.md` for German read by a learner) unless a comment says «natural» — a line written here
 * where the run had none of the kind (an abbreviation, a negated move, a split number). Every pack is the deployment's own
 * ({@see lessonPacks()}); a row that needs a neighbour's pack skips until that pack writes its letters and frequent words.
 */

// Canon (pack-keys §7.2, the pairs of the order: ru → de and de → en): every reader of either side reads the pack in the
// shape it reads it. CATCHES a key written as null or in the wrong shape (a `LanguagePackKeyMissing` from a reader), and a
// key LANG-1 asks of a pack that is both sides left out.
it('gives every reader of the pack what it reads, in the shape it reads it', function (string $code) {
    $pack = lessonPacks()->for($code);
    $words = new LanguageWords($pack);

    expect(LanguageRoles::planTargets())->toContain($code)
        ->and(LanguageRoles::planNatives())->toContain($code);
    foreach (['abbreviations', 'question_word_order', 'unstressed_words', 'number_words', 'number_joiners', 'number_tens_joiners', 'irregular_forms', 'inflection_rules', 'person_swap', 'contractions', 'contractions_before', 'intro_words', 'clause_starters', 'negation', 'partitive', 'dangling_words', 'rescue_line', 'neutral_reply', 'script_letters', 'common_words', 'amount_pattern', 'amount_prefix', 'talk_title_template'] as $key) {
        expect($pack->has($key))->toBeTrue("the pack reads `{$key}`");
    }
    expect(NumberValues::of($pack))->not->toBeNull()
        ->and($pack->talkTitleTemplate())->not->toBeNull();
    // Each call throws on a key written in the wrong shape.
    (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
    (new FrameJudge)->breaksOff('a b', [], $pack);
    (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
    WordBases::of('abc', $pack);
    $words->isQuestion('a b');
    $words->genderedPast('a b');
    $words->foreignLetters('ab');
})->with(['de']);

// Canon (CHECK-1, LANG-1 §1): an abbreviation's dot ends no sentence inside a text and closes a text at its very end; the
// German quotes „…“ close a sentence like any other. CATCHES «Nr.», «z. B.», «Dr.», «ca.» cutting a partner's line into two
// sentences (a sentence count, a filler «ca. 16 Uhr» read as carrying a sentence of its own — `filler.ungrammatical`).
it('ends a German sentence where German ends it, an abbreviation\'s dot aside', function () {
    $ends = new SentenceEnds(lessonPacks()->for('de'));

    // Real lines of ru-de (A1, A8, A7) and de-en (A1), two with an abbreviation put in (natural).
    expect($ends->count('Gern. Worum geht es?'))->toBe(2)
        ->and($ends->terminalKind('Gern. Worum geht es?'))->toBe('question')
        ->and($ends->count('Ja, die Klinik ist in der Bahnhofstraße Nr. 12.'))->toBe(1)
        ->and($ends->terminal('Ja, die Klinik ist in der Bahnhofstraße Nr. 12.'))->toBe('.')
        ->and($ends->count('Bitte bringen Sie Ihre Versicherungskarte, z. B. die Karte der AOK, mit.'))->toBe(1)
        ->and($ends->count('Natürlich. Was ist denn das Problem?'))->toBe(2)
        ->and($ends->count('Dr. Weber hat um ca. 16 Uhr noch einen Termin frei.'))->toBe(1)
        ->and($ends->count('„Um elf passt besser.“ Das hat der Patient gesagt.'))->toBe(2)
        ->and($ends->terminal('Bringen Sie Ihren Ausweis, die Karte usw.'))->toBe('.')
        // The weekdays of opening hours (natural): «Mo.» … «Sa.» end nothing; «So.» is not listed (the word «so»).
        ->and($ends->count('Wir haben Mo. bis Fr. geöffnet.'))->toBe(1)
        ->and($ends->count('Ja, so. Kommen Sie morgen.'))->toBe(2)
        ->and($ends->carriesSentence('ca. 16 Uhr'))->toBeFalse()
        ->and($ends->carriesSentence('morgen um vier'))->toBeFalse()
        ->and($ends->carriesSentence('morgen um vier.'))->toBeTrue();
});

// Canon (FIX-4 §2, LANG-1 §1): a frame is said as a coherent phrase where the move begins or after its opening words; a
// German negation — «nicht», «kein…» — is free anywhere inside the frame's words; one other word is «almost». The frames
// and lines of ru-de day 1 (p1, p3, p5, p7; the variant of B1). CATCHES «nicht» counted as a word added (the negated move
// «almost» instead of said), «Ja» / «Ja gerne» / «Alles klar» blocking a frame that starts after them, the recogniser's
// «hab» / «gibt's» read as other words than the frame's «habe» / «gibt es», and a variant with another verb passing for
// the frame.
it('judges German moves against the frames of the scouting day', function () {
    $de = lessonPacks()->for('de');
    $one = static fn (string $heard, string $frame): MoveVerdict => (new FrameJudge)->move($heard, [new ConversationPhrase('x', 'p1', $frame, '', null, null)], $de);

    expect($one('Ich brauche einen Termin.', 'Ich brauche ___.'))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'einen Termin']))
        ->and($one('Das habe ich seit drei Tagen.', 'Das habe ich ___.'))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'seit drei Tagen']))
        ->and($one('Ich bringe meine Versicherungskarte mit.', 'Ich bringe ___ mit.'))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'meine Versicherungskarte']))
        // Negated (natural): «nicht» between «Sie» and «etwas» is no difference; the window is the learner's value.
        ->and($one('Haben Sie nicht etwas am Nachmittag?', 'Haben Sie etwas ___?'))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'am Nachmittag']))
        ->and($one('Ich bringe meinen Ausweis nicht mit.', 'Ich bringe ___ mit.')->said)->toBe(['x:p1'])
        // The variant the model wrote for B1: another verb — one word of the frame replaced, almost.
        ->and($one('Ich möchte einen Termin.', 'Ich brauche ___.'))->toEqual(new MoveVerdict([], ['x:p1']))
        // Opening words (natural): the frame starts after them.
        ->and($one('Ja, das habe ich seit drei Tagen.', 'Das habe ich ___.')->said)->toBe(['x:p1'])
        ->and($one('Guten Tag, ich brauche einen Termin.', 'Ich brauche ___.')->said)->toBe(['x:p1'])
        ->and($one('Ja gerne, das habe ich seit drei Tagen.', 'Das habe ich ___.')->said)->toBe(['x:p1'])
        ->and($one('Alles klar, ich bringe meine Versicherungskarte mit.', 'Ich bringe ___ mit.')->said)->toBe(['x:p1'])
        // Spoken short forms (natural; the frame p2 and the variant of B5 «Gibt es etwas am Nachmittag?»): «hab» is «habe»,
        // «gibt's» / «gibts» is «gibt es» — the recogniser's spelling of what the learner said.
        ->and($one('Ich hab Halsschmerzen.', 'Ich habe ___.'))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'Halsschmerzen']))
        ->and($one("Gibt's etwas am Nachmittag?", 'Gibt es etwas ___?'))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'am Nachmittag']))
        ->and($one('Gibts etwas am Nachmittag', 'Gibt es etwas ___?')->said)->toBe(['x:p1'])
        // The article stays in the comparison (DECISIONS п. 89): a frame's article said in another gender is a difference.
        ->and($one('Können Sie mir den Adresse sagen?', 'Können Sie mir die Adresse sagen?'))->toEqual(new MoveVerdict([], ['x:p1']));
});

// Canon (FIX-2 п. 2, FIX-3 §4, LANG-1 §4): a number said in words is the number in digits on both sides — German's one-word
// 21–99 and hundreds, «dreißig» as the text folds it, a number split by a recogniser read as one. The article «eine» is no
// number (DECISIONS п. 89: the gender is not forgiven). CATCHES a compound left as a word, «ß» in a list meeting «ss» in
// the text, and «eine Woche» read as «1 woche».
it('reads German numbers said in words as digits, with the pack\'s own lists', function () {
    $speech = lessonPacks()->for('de')->speech();
    $fold = static fn (string $text): string => implode(' ', SpokenNumbers::fold(
        explode(' ', (new LexicalNormalizer)->canonicalize($text)),
        $speech->numberWords, $speech->articles, $speech->numberJoiners, $speech->numberTensJoiners,
    ));

    // Real lines: ru-de B3, A4, A8; de-en B7.
    expect($fold('Das habe ich seit drei Tagen.'))->toBe('das habe ich seit 3 tagen')
        ->and($fold('Wir haben morgen um neun oder um elf frei.'))->toBe('wir haben morgen um 9 oder um 11 frei')
        ->and($fold('Ja, die Klinik ist in der Bahnhofstraße zwölf.'))->toBe('ja die klinik ist in der bahnhofstrasse 12')
        ->and($fold('Ich komme fünfzehn Minuten früher.'))->toBe('ich komme 15 minuten früher')
        // Natural: the one-word compounds, a split hundred, the joiner after a scale, ß folded.
        ->and($fold('Das kostet einundzwanzig Euro, nicht dreißig.'))->toBe('das kostet 21 euro nicht 30')
        ->and($fold('Zimmer zweihundert vierunddreißig'))->toBe('zimmer 234')
        ->and($fold('zwei hundert'))->toBe('200')
        ->and($fold('tausend und eins'))->toBe('1001')
        ->and($fold('Meine Nummer ist null eins sieben sechs'))->toBe('meine nummer ist 0 1 7 6')
        ->and($fold('Ich habe das seit einer Woche.'))->toBe('ich habe das seit einer woche')
        // A scale with its «one» split off by a recogniser (natural) is the one-word number: «einhundert», «eine Million».
        ->and($fold('Das kostet ein hundert Euro.'))->toBe('das kostet 100 euro')
        ->and($fold('Das kostet einhundert Euro.'))->toBe('das kostet 100 euro')
        ->and($fold('eine Million Euro'))->toBe('1000000 euro')
        ->and($fold('zwischen zwanzig und dreißig Euro'))->toBe('zwischen 20 und 30 euro')
        ->and($speech->articles)->toBe([])
        ->and($speech->unstressed)->not->toContain('der', 'die', 'das', 'den', 'dem', 'ein', 'eine', 'einen', 'nicht', 'kein', 'ohne', 'sein');

    // A line the learner reads aloud (SpeechMode::Repeat): the recogniser's «muss» for «muß» and a dropped «um» are
    // forgiven; the possessive's gender and «mit» for the line's «ohne» are not (natural lines on ru-de B4, B7).
    $match = new SpeechMatch;
    expect($match->repeated('elf passt besser', 'Um elf passt besser.', $speech))->toBeTrue()
        ->and($match->repeated('Ich muss um vier da sein', 'Ich muß um vier da sein.', $speech))->toBeTrue()
        ->and($match->repeated('Ich bringe meinen Versicherungskarte mit', 'Ich bringe meine Versicherungskarte mit.', $speech))->toBeFalse()
        ->and($match->repeated('Ich komme mit Termin', 'Ich komme ohne Termin.', $speech))->toBeFalse();
});

// Canon (FIX-4c §6, LANG-1 §5): a German learner's grey line in German passes the guard; one in English — the target of
// de → en — is no translation. The German lines are the text_native of de-en day 1 (A1, A2, A3, A4, A5, A8). CATCHES a
// frequent German word missing from the pack (a German line read as nobody's), and a word of the German list that is
// ordinary in a neighbour language (an honest line refused).
it('lets a German translation through the guard and refuses an English one', function () {
    $de = lessonPacks()->for('de');

    foreach ([
        ['Of course. What seems to be the problem?', 'Natürlich. Was ist denn das Problem?'],
        ['What symptoms do you have?', 'Welche Beschwerden haben Sie?'],
        ['How long have you had that?', 'Wie lange haben Sie das schon?'],
        ['We have appointments at ten or at two today.', 'Wir haben heute Termine um zehn oder um zwei.'],
        ['I need your full name, please.', 'Ich brauche bitte Ihren vollständigen Namen.'],
        ['Yes, it\'s 14 King Street.', 'Ja, das ist King Street 14.'],
    ] as [$target, $native]) {
        expect(ReplyNative::missing($target, $native, $de))->toBeFalse("«{$native}» is German");
    }

    if (lessonPacks()->for('en')->commonWords() === []) {
        $this->markTestSkipped('the English pack writes no common_words yet');
    }
    // English under a German learner: the role's next line said in English instead of translated (de-en A2, A5).
    expect(ReplyNative::missing('What symptoms do you have?', 'How long have you had that?', $de))->toBeTrue()
        ->and(ReplyNative::missing('And what is your phone number?', 'I need your full name, please.', $de))->toBeTrue();
});

// Canon (LANG-1 §5): the same guard, the other way round — a German line under a learner of another Latin language is no
// translation, and that learner's own line is not read as German. CATCHES a German list that makes a Spanish learner's
// honest line look German. Natural lines; skips until the Spanish pack writes its frequent words.
it('tells German apart for a Spanish learner too', function () {
    $es = lessonPacks()->for('es');
    if ($es->commonWords() === []) {
        $this->markTestSkipped('the Spanish pack writes no common_words yet');
    }

    expect(ReplyNative::missing('How long have you had that?', 'Ich habe das seit drei Tagen und ich habe auch Fieber.', $es))->toBeTrue()
        ->and(ReplyNative::missing('How long have you had that?', '¿Desde cuándo lo tiene? ¿Está peor hoy?', $es))->toBeFalse();
});

// Canon (LANG-1 §6): a German learner's talk is titled without declension, the roles as written — German nouns keep their
// capital. CATCHES the English fallback («Talk to the Empfangskraft») and a role lower-cased mid-title.
it('titles a German learner\'s talk with its roles as German writes them', function () {
    $de = lessonPacks()->for('de');
    $title = new NativeStrings('de');

    expect($title->talkTitle(['Empfangskraft', 'Arzt'], $de))->toBe('Gespräch: Empfangskraft und Arzt')
        ->and($title->talkTitle(['Empfangskraft', 'Arzt', 'MRT-Assistentin'], $de))->toBe('Gespräch: Empfangskraft, Arzt und MRT-Assistentin')
        ->and($title->talkTitle(['Arzt'], $de))->toBe('Gespräch: Arzt')
        ->and($title->talkTitle([], $de))->toBe('Gespräch');
});

// Canon (FATAL keys, pack-keys §6): a German question is known by its word order narrowly: «Können Sie…», «Tut es…» ask
// without a mark, a doctor's imperative «Haben Sie keine Angst.» and a statement do not. CATCHES a list that misreads
// healthy German: «haben» + «Sie» read as a question, the condition «Sollten Sie Fragen haben, …» read as a question.
it('reads a German question by its order narrowly', function () {
    $words = new LanguageWords(lessonPacks()->for('de'));

    expect($words->isQuestion('Können Sie mir die Adresse sagen'))->toBeTrue()
        ->and($words->isQuestion('Tut es noch weh'))->toBeTrue()
        ->and($words->isQuestion('Haben Sie etwas am Nachmittag?'))->toBeTrue()
        ->and($words->isQuestion('Haben Sie keine Angst.'))->toBeFalse()
        ->and($words->isQuestion('Werden Sie bald gesund!'))->toBeFalse()
        ->and($words->isQuestion('Ja, um vier Uhr ist noch ein Termin frei.'))->toBeFalse()
        ->and($words->isQuestion('Gut, dann trage ich Sie morgen um vier ein.'))->toBeFalse()
        // The condition that opens with its verb (natural closing lines of a receptionist and a doctor): no question.
        ->and($words->isQuestion('Sollten Sie Fragen haben, rufen Sie uns an.'))->toBeFalse()
        ->and($words->isQuestion('Sollte es schlimmer werden, kommen Sie bitte wieder.'))->toBeFalse()
        ->and($words->isQuestion('Hat mich gefreut.'))->toBeFalse()
        ->and($words->isQuestion('Soll ich meine Versicherungskarte mitbringen'))->toBeTrue();

    // The polite «hätte», «wäre» carry no content.
    expect($words->content('Ich hätte gern einen Arzttermin.'))->toBe(['arzttermin'])
        ->and($words->content('Wäre das möglich?'))->toBe(['möglich']);
});

// Canon (BACK-TAILS-1 §3.2, LANG-1 §8): a German learner reads the target in Latin letters — a Cyrillic letter in the reading
// is fatal (`script_letters`), a Latin letter outside German's alphabet is none. The Cyrillic reading is the scouting run's
// own (de-en B4, written under v4.7). CATCHES a strict alphabet in the fatal key (an IPA «ə» failing a day) and a Latin
// pattern that lets Cyrillic through.
it('reads a German learner\'s reading in Latin letters', function () {
    $words = new LanguageWords(lessonPacks()->for('de'));

    expect($words->foreignLetters('tu oklokk is better fo ми'))->toBe(['м', 'и'])
        ->and($words->foreignLetters('ai laik e doktors äpointment'))->toBe([])
        ->and($words->foreignLetters('ə sor θrout'))->toBe(['θ'])
        ->and($words->foreignLetters('ə sor srout'))->toBe([]);
});

// Canon (BACK-TAILS-2 §9): the role that says the learner's move back — «Ich habe seit drei Tagen Halsschmerzen» answered
// «Sie haben also seit drei Tagen Halsschmerzen.» — is an echo: read with the persons swapped (ich → Sie) and the verb by its
// bases (habe = haben). Natural lines on the scouting day's facts. CATCHES a swap that misses the polite «Sie» and verb forms
// that never meet, and an echo found where the role asks something new.
it('reads a German role\'s echo of the learner with the persons swapped', function () {
    $de = lessonPacks()->for('de');
    $share = new LineShare;

    expect($share->share('Sie haben also seit drei Tagen Halsschmerzen.', 'Ich habe seit drei Tagen Halsschmerzen', $de, swapPersons: true))->toBeGreaterThanOrEqual(0.7)
        ->and($share->share('Sie haben also seit drei Tagen Halsschmerzen.', 'Ich habe seit drei Tagen Halsschmerzen', $de))->toBeLessThan(0.7)
        ->and($share->share('Haben Sie auch Fieber oder Husten?', 'Ich habe seit drei Tagen Halsschmerzen', $de, swapPersons: true))->toBeLessThan(0.7)
        ->and(WordBases::meet('tagen', 'tag', $de))->toBeTrue()
        ->and(WordBases::meet('gebraucht', 'brauchst', $de))->toBeTrue()
        ->and(WordBases::meet('hat', 'habe', $de))->toBeTrue()
        ->and(WordBases::meet('termin', 'fieber', $de))->toBeFalse();
});

// Canon (SESSION-1a «Поймай число», BACK-TAILS-1 §1.3): a German line's amount is offered as the line says it, with its
// preposition — the options of de-en's listening («Seit drei Tagen», «Fünfzehn Minuten früher»); a date is no amount.
// CATCHES a unit missing from the time words (the option cut to «Fünfzehn»), a bare noun offered without its «seit», the
// greeting «Guten Tag» offered as an amount, and the verb «achten» read as the ordinal.
it('reads the amounts of a German line as the listening offers them', function () {
    $values = NumberValues::of(lessonPacks()->for('de'));

    expect($values?->value('Ich habe das seit drei Tagen.'))->toBe(['text' => 'Seit drei Tagen', 'number' => true])
        ->and($values?->value('Bitte kommen Sie fünfzehn Minuten früher wegen des Formulars.'))->toBe(['text' => 'Fünfzehn Minuten früher', 'number' => true])
        ->and($values?->value('Ich habe das seit einer Woche.'))->toBe(['text' => 'Seit einer Woche', 'number' => false])
        ->and($values?->value('Haben Sie etwas am Freitag?'))->toBeNull()
        // The greeting is no amount to offer (natural): the option is the hour, never «Tag».
        ->and($values?->value('Guten Tag, ich habe um vier einen Termin.'))->toBe(['text' => 'Um vier', 'number' => true])
        // The verb «achten» is no ordinal (natural doctor's line): the line says no number, and so it cannot become the line
        // of «Поймай число» whose translation names none — which left the scene without the card.
        ->and($values?->says('Bitte achten Sie darauf, viel zu trinken.'))->toBeFalse()
        ->and($values?->value('Kommen Sie morgen früh um acht.'))->toBe(['text' => 'Um acht', 'number' => true])
        ->and($values?->runs('Kommen Sie morgen früh um acht.')[0]['text'] ?? null)->toBe('morgen früh');
});
