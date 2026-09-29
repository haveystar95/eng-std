<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Assembly\NumberValues;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\Service\LineShare;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\ReplyNative;
use App\Modules\Plan\Domain\Service\WordBases;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\MoveVerdict;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\Service\SpokenNumbers;

/**
 * THE SPANISH PACK (наряд LANG-1, `config/lesson/lang/es.php`) — Spanish is a TARGET (ru→es) and a LEARNER'S OWN language
 * (es→en). Its canon is read on the lines of the order's scouting days (`docs/research/lang-1/days/ru-es.md`, `es-en.md`,
 * model `gpt-5.4`, `lesson_day.v4.7`): the learner's lines against their frames, the role's lines, the numbers they say,
 * the translations of the es→en day. A line not taken from there is marked so where it stands.
 */

// Canon (pack-keys §7.2): Spanish is in both lists of the plan, and every reader of either side (ru→es and es→en) takes
// its key in the shape it reads. CATCHES a key left null or unwritten, and a key in a shape that throws in the talk, the
// speech or the cards.
it('gives every reader of the Spanish pack what it reads, on both sides', function () {
    $code = 'es';
    $pack = lessonPacks()->for($code);
    $words = new LanguageWords($pack);

    expect(LanguageRoles::planTargets())->toContain($code)
        ->and(LanguageRoles::planNatives())->toContain($code);

    // The target side.
    foreach (['abbreviations', 'question_word_order', 'unstressed_words', 'number_words', 'number_joiners', 'number_tens_joiners', 'irregular_forms', 'inflection_rules', 'person_swap', 'contractions', 'contractions_before', 'intro_words', 'clause_starters', 'negation', 'partitive', 'dangling_words', 'rescue_line', 'neutral_reply', 'script_letters', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the target side reads `{$key}`");
    }
    expect(NumberValues::of($pack))->not->toBeNull()
        ->and($pack->rescueLine())->toBe('¿Perdón?')
        ->and($pack->neutralReply())->toBe('Entiendo. Continúe, por favor.');
    (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
    (new FrameJudge)->breaksOff('a b', [], $pack);
    (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
    WordBases::of('abc', $pack);
    $words->isQuestion('a b');

    // The learner's side.
    foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
    }
    expect($pack->talkTitleTemplate())->not->toBeNull()
        ->and((new NativeStrings($code))->talkTitle(['Recepcionista', 'MRI'], $pack))->toContain('MRI');
    $words->genderedPast('a b');
    $words->foreignLetters('ab');

    // No key is null: a null reads as unwritten (the rule that needs it does not run), the spec's no-op never does.
    $written = require dirname(__DIR__, 4).'/config/lesson/lang/es.php';
    expect(array_keys(array_filter($written, static fn (mixed $value): bool => $value === null)))->toBe([]);
});

// Canon (наряд CHECK-1, LANG-1 §1): a sentence ends at . ? ! …, the Spanish ¿ ¡ open one and end none, and the dot of an
// abbreviation of the pack ends nothing inside a text and closes it at its very end. The role's lines of both scouting
// days; the last three put the pack's abbreviations into a line of the ru→es day (not the model's words). CATCHES a
// «¿…?» read as two sentences, «la Dra. Ruiz» or «a las 4 p. m.» cutting a line in two, and a filler «a las 4 p. m.»
// read as carrying a sentence of its own — the fatal `filler.ungrammatical` on healthy Spanish.
it('ends a Spanish sentence at its marks, past ¿ ¡ and the dots of its abbreviations', function () {
    $ends = lessonPacks()->for('es')->sentenceEnds();

    expect($ends->count('Claro. ¿Para qué día la necesita?'))->toBe(2)
        ->and($ends->terminalKind('Claro. ¿Para qué día la necesita?'))->toBe('question')
        ->and($ends->count('Sí, hoy a las cuatro de la tarde.'))->toBe(1)
        ->and($ends->terminalKind('Sí, hoy a las cuatro de la tarde.'))->toBe('statement')
        ->and($ends->count('Entiendo. Entonces es una cita urgente.'))->toBe(2)
        ->and($ends->count('Tienes cita hoy a las dos. Por favor, llega diez minutos antes.'))->toBe(2)
        ->and($ends->count('¡Perfecto! Su cita está confirmada para hoy.'))->toBe(2)
        ->and($ends->carriesSentence('¿La cita es a las cuatro?'))->toBeTrue()
        // The abbreviations — a line of the ru→es day with them put in.
        ->and($ends->count('Su cita con la Dra. Ruiz está confirmada para hoy a las 4 p. m. o a las 5 p.m.'))->toBe(1)
        ->and($ends->terminal('Su cita con la Dra. Ruiz está confirmada para hoy a las 4 p. m.'))->toBe('.')
        ->and($ends->carriesSentence('a las 4 p. m.'))->toBeFalse()
        ->and($ends->count('¿Sr. García? Pase, por favor.'))->toBe(2)
        // «EE. UU.» (the RAE's plural, with its space) and a title of the role are no ends either — a filler «EE. UU.»
        // carried a sentence of its own before the review, the fatal `filler.ungrammatical` on a trip or an address.
        ->and($ends->carriesSentence('EE. UU.'))->toBeFalse()
        ->and($ends->carriesSentence('EE.UU.'))->toBeFalse()
        ->and($ends->count('Vivo en EE. UU. desde hace un año.'))->toBe(1)
        ->and($ends->count('Su cita con el Lic. Gómez es en la hab. 12.'))->toBe(1);
});

// Canon (наряд FIX-4 §2, FIX-4b §1, LANG-1 §1): a frame is said as a coherent phrase — its words where the move begins,
// after the opening words, or after a conjunction; the articles out of the comparison, «al» spelt «a el»; «no» free
// anywhere; one difference is «almost». The learner's lines of the ru→es day against its frames p1–p8 (the negated line
// and the glued two are the day's frames with their own fillers; «Voy al médico» is written here). CATCHES a Spanish line
// read through another language's lists — «una» of «una cita» counted as a word of the frame, «¿» kept in a word, «No»
// counted as a word added, «al» not «a», and «Quiero una cita» (the model's own simpler variant of B1) called said.
it('judges the learner\'s Spanish lines against their frames: said, said in the negative, almost', function () {
    $es = lessonPacks()->for('es');
    $frames = [];
    foreach ([
        'p1' => 'Necesito ___.', 'p2' => 'Prefiero ___.', 'p3' => 'Tengo ___.', 'p4' => 'También tengo ___.',
        'p5' => 'Mi nombre es ___.', 'p6' => 'Mi número es ___.', 'p7' => '¿La cita es ___?', 'p8' => 'Muchas gracias.',
    ] as $ref => $frame) {
        $frames[] = new ConversationPhrase('s1', $ref, $frame, '', null, null);
    }
    $judge = static fn (string $heard, array $of = []): MoveVerdict => (new FrameJudge)->move($heard, $of === [] ? $frames : $of, $es);
    $onlyP4 = [new ConversationPhrase('s1', 'p4', 'También tengo ___.', '', null, null)];
    $voy = [new ConversationPhrase('s2', 'p1', 'Voy a ___.', '', null, null)];

    expect($judge('Necesito una cita.'))->toEqual(new MoveVerdict(['s1:p1'], [], ['s1:p1' => 'una cita']))
        ->and($judge('Tengo dolor de garganta.'))->toEqual(new MoveVerdict(['s1:p3'], [], ['s1:p3' => 'dolor de garganta']))
        ->and($judge('Mi nombre es Ivan Petrov.'))->toEqual(new MoveVerdict(['s1:p5'], [], ['s1:p5' => 'Ivan Petrov']))
        ->and($judge('¿La cita es a las cuatro?'))->toEqual(new MoveVerdict(['s1:p7'], [], ['s1:p7' => 'a las cuatro']))
        ->and($judge('Muchas gracias.')->said)->toBe(['s1:p8'])
        // In the negative: «no» before the verb, the first word of the move, is free.
        ->and($judge('No tengo fiebre.'))->toEqual(new MoveVerdict(['s1:p3'], [], ['s1:p3' => 'fiebre']))
        // Almost: another verb for the frame's own, or its «También» left out.
        ->and($judge('Quiero una cita.')->said)->toBe([])
        ->and($judge('Quiero una cita.')->almost)->toContain('s1:p1')
        ->and($judge('Tengo temperatura.', $onlyP4))->toEqual(new MoveVerdict([], ['s1:p4']))
        // The opening words, and two constructions glued by «y».
        ->and($judge('Hola, buenos días, necesito una cita.')->values)->toBe(['s1:p1' => 'una cita'])
        ->and($judge('Tengo dolor de garganta y también tengo temperatura.'))->toEqual(new MoveVerdict(['s1:p3', 's1:p4'], [], ['s1:p3' => 'dolor de garganta', 's1:p4' => 'temperatura']))
        // «al» is «a el».
        ->and($judge('Voy al médico.', $voy)->said)->toBe(['s2:p1'])
        // The review's lines (not the model's): «además» opens a clause as «y» does, «perdona» (tú) opens a move as
        // «perdone» does, and the role is addressed before the construction.
        ->and($judge('Necesito una cita, además tengo dolor de garganta.'))->toEqual(new MoveVerdict(['s1:p1', 's1:p3'], [], ['s1:p1' => 'una cita', 's1:p3' => 'dolor de garganta']))
        ->and($judge('Perdona, necesito una cita.')->values)->toBe(['s1:p1' => 'una cita'])
        ->and($judge('Doctora, tengo dolor de garganta.')->values)->toBe(['s1:p3' => 'dolor de garganta'])
        // A move that stops on an article or where the window opens broke off.
        ->and((new FrameJudge)->breaksOff('Necesito una', $frames, $es))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Tengo dolor de', $frames, $es))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Mi nombre es', $frames, $es))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Tengo dolor de garganta', $frames, $es))->toBeFalse()
        // A possessive of its own ends a Spanish sentence; «¿Y tú?», «para mí» with their accents too. (The hour «a la una»
        // does read as broken off — the lenient side, see the pack's `dangling_words`.)
        ->and((new FrameJudge)->breaksOff('La decisión es nuestra', $frames, $es))->toBeFalse()
        ->and((new FrameJudge)->breaksOff('¿Y tú?', $frames, $es))->toBeFalse()
        ->and((new FrameJudge)->breaksOff('Es para mí', $frames, $es))->toBeFalse();
});

// Canon (наряд FIX-3 §4, LANG-1 §4): the words of one number are one number — «y» joins a unit to a tens word, a scale
// multiplies, «veintiún» and «treinta y un» are single entries, «un»/«una» alone stay articles. The role's lines of both
// days and the reading of the phone number of B6 (ru→es: «нуэвэ уно дос» — nine, one, two) first; then the joining rule.
// And «Поймай число» on the learner's side reads the amounts of the es→en day as the line says them — and no greeting,
// no «¿a qué hora?» as an amount (the review: «Buenos días, tiene cita a las diez» gave «Días» for the right answer, the
// first of two runs of one word). CATCHES «treinta y uno» left as «30 y 1», «una cita» read as «1 cita», «las cinco y
// media» joined into a number, an option «Media», and «Días», «Día», «Hora» offered as amounts.
it('reads the numbers of the Spanish lines as the digits a recogniser writes, and the amounts as the line says them', function () {
    $es = lessonPacks()->for('es');
    $speech = $es->speech();
    $fold = static fn (string $text): string => implode(' ', SpokenNumbers::fold(
        explode(' ', (new LexicalNormalizer)->canonicalize($text)),
        $speech->numberWords, $speech->articles, $speech->numberJoiners, $speech->numberTensJoiners,
    ));
    $amounts = NumberValues::of($es);

    expect($fold('Tenemos hoy a las cuatro o mañana a las diez.'))->toBe('tenemos hoy a las 4 o mañana a las 10')
        ->and($fold('Por favor, llega diez minutos antes.'))->toBe('por favor llega 10 minutos antes')
        ->and($fold('Llevo tres días con eso.'))->toBe('llevo 3 días con eso')
        ->and($fold('Mi número es nueve uno dos'))->toBe('mi número es 9 1 2')
        ->and($fold('Necesito una cita.'))->toBe('necesito una cita')
        ->and($fold('ciento treinta y uno'))->toBe('131')
        ->and($fold('cuarenta y cinco segundos'))->toBe('45 segundos')
        ->and($fold('treinta y un días'))->toBe('31 días')
        ->and($fold('veintiún años'))->toBe('21 años')
        ->and($fold('dieciséis'))->toBe('16')
        ->and($fold('dos mil veinte'))->toBe('2020')
        ->and($fold('doscientas cincuenta personas'))->toBe('250 personas')
        ->and($fold('un millón de euros'))->toBe('1000000 de euros')
        ->and($fold('las cinco y media'))->toBe('las 5 y media')
        ->and($fold('uno y dos'))->toBe('1 y 2')
        ->and($amounts?->value('Por favor, llega diez minutos antes.'))->toBe(['text' => 'Diez minutos antes', 'number' => true])
        ->and($amounts?->values('Tenemos citas hoy a las diez y a las dos.'))->toBe([['text' => 'A las diez', 'number' => true], ['text' => 'A las dos', 'number' => true]])
        ->and($amounts?->value('Llevo tres días con eso.'))->toBe(['text' => 'Tres días', 'number' => true])
        ->and($amounts?->value('Buenos días, tiene cita a las diez.'))->toBe(['text' => 'A las diez', 'number' => true])
        ->and($amounts?->value('¿A qué hora le viene bien, a las diez o a las dos?'))->toBe(['text' => 'A las diez', 'number' => true])
        ->and($amounts?->values('Buenos días. ¿A qué hora le viene bien?'))->toBe([])
        ->and($amounts?->values('Que tenga un buen día.'))->toBe([])
        ->and($amounts?->value('Espere unos minutos, por favor.'))->toBe(['text' => 'Unos minutos', 'number' => false])
        // A LIMIT, not the canon: a value is a run of number and time words with nothing but spaces between
        // (NumberValues::GAP), so «y» cuts «las cinco y media» at «Las cinco» — an option that says another hour. No key of
        // the pack joins it (a «y» read as a time word would make every «y» a value); the kernel's to mend.
        ->and($amounts?->values('Las cinco y media'))->toBe([['text' => 'Las cinco', 'number' => true]]);
});

// Canon (наряд FIX-4c §6, LANG-1 §5): the role's grey line under a Spanish learner is a translation when it is Spanish —
// the es→en day's own translations, with the deployed packs and their Latin neighbours — and missing when it is English:
// the day's English lines, the ones a mini model sends back untranslated. The Spanish `common_words` are frequent AND
// distinctive, so a line with none of them («Las diez no me vienen bien.», «¿Me dices tu nombre, por favor?») still
// holds. CATCHES a Spanish list that hands «de», «la», «no», «me», «bien» to a neighbour (or takes theirs), turning the
// pair's honest translations into `native_missing`, and a guard that lets English through for a Spanish learner.
it('keeps the es→en day\'s Spanish translations and refuses its English lines under a Spanish learner', function () {
    $es = lessonPacks()->for('es');
    $refused = array_values(array_filter([
        'Claro. ¿Qué problema tienes?',
        '¿Cuánto tiempo llevas con eso?',
        'Tenemos citas hoy a las diez y a las dos.',
        'Las diez no me vienen bien.',
        'Sí, las dos están libres.',
        'No, pero las dos de hoy siguen libres.',
        '¿Me dices tu nombre, por favor?',
        'Tienes cita hoy a las dos. Por favor, llega diez minutos antes.',
    ], static fn (string $line): bool => ReplyNative::missing('—', $line, $es)));

    expect($refused)->toBe([])
        ->and(ReplyNative::missing('—', 'How long have you had that?', $es))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Can I have your name, please?', $es))->toBeTrue();
});

// Canon (наряд LANG-1 §6, `talk_title_template`): a Spanish talk is named without putting a role in a case —
// «Conversación: recepcionista y médico» (the es→en plan's roles), the first letters lowered, an acronym kept; «y» is
// «e» before the sound i- («médico e internista»), and the title is «Conversación» when no role is named. CATCHES the
// English fallback «Talk to the recepcionista and the médico», a role written «Recepcionista» mid-title, and «y internista».
it('names a talk in Spanish by its roles, «y» turning «e» before i-', function () {
    $es = lessonPacks()->for('es');
    $title = new NativeStrings('es');

    expect($title->talkTitle(['Recepcionista', 'Médico'], $es))->toBe('Conversación: recepcionista y médico')
        ->and($title->talkTitle(['Médico', 'Internista'], $es))->toBe('Conversación: médico e internista')
        ->and($title->talkTitle(['Recepcionista', 'Médico', 'Enfermera'], $es))->toBe('Conversación: recepcionista, médico y enfermera')
        ->and($title->talkTitle(['Recepcionista', 'MRI'], $es))->toBe('Conversación: recepcionista y MRI')
        ->and($title->talkTitle([], $es))->toBe('Conversación');
});

// Canon (READINGS on the learner's side, es→en): a reading in Cyrillic (the day's readings) is another writing, fatal; an
// honest Latin reading with its accents and the Spanish ¿ is none; a Spanish line says no gendered past of the learner.
// CATCHES a Spanish `script_letters` that fails an honest Latin reading or passes a Cyrillic one.
it('reads the es→en readings and native lines the way Spanish is written', function () {
    $words = new LanguageWords(lessonPacks()->for('es'));

    expect($words->foreignLetters('ай хэв ___'))->toBe(['а', 'й', 'х', 'э', 'в'])
        ->and($words->foreignLetters('ái jav a sor zróut, ¿sí? ___'))->toBe([])
        ->and($words->genderedPast('Yo fui al médico ayer.'))->toBe([]);
});
