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
 * THE FRENCH PACK (наряд LANG-1, `config/lesson/lang/fr.php`) — French is both sides of a plan: taught (ru→fr) and spoken
 * by the learner (fr→en). Every reader of the pack gets its keys in the shape it reads, and the canon is held on the lines
 * of the scouting run of the order (`docs/research/lang-1/days/ru-fr.md`, `fr-en.md`, the model's raw answers
 * `answers/ru-fr.json`, `answers/fr-en.json`): real sentences of the model, the learner's lines against the frames of
 * that day, the numbers it says, the translations of the role.
 */

// Canon (pack-keys §7.2): the ru→fr target side and the fr→en native side find every key they read, and no reader throws
// on a key written in the wrong shape. CATCHES a key left null or absent and a key the reader cannot read (a string for a
// list, a missing field).
it('gives every reader of the pack what it reads, in the shape it reads it', function (string $code) {
    $pack = lessonPacks()->for($code);
    $words = new LanguageWords($pack);

    expect(LanguageRoles::planTargets())->toContain($code)
        ->and(LanguageRoles::planNatives())->toContain($code);

    // The target side (ru→fr).
    foreach (['abbreviations', 'question_word_order', 'unstressed_words', 'number_words', 'number_joiners', 'irregular_forms', 'inflection_rules', 'person_swap', 'contractions', 'contractions_before', 'intro_words', 'clause_starters', 'negation', 'partitive', 'dangling_words', 'rescue_line', 'neutral_reply', 'script_letters', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the target side reads `{$key}`");
    }
    expect(NumberValues::of($pack))->not->toBeNull();
    (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
    (new FrameJudge)->breaksOff('a b', [], $pack);
    (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
    WordBases::of('abc', $pack);
    $words->isQuestion('a b');

    // The native side (fr→en).
    foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
    }
    expect($pack->talkTitleTemplate())->not->toBeNull()
        ->and((new NativeStrings($code))->talkTitle(['Recepcjonistka', 'MRI'], $pack))->toContain('MRI');
    $words->genderedPast('a b');
    $words->foreignLetters('ab');

    // No key is null: null is «not written», and the rule that needs it does not run (наряд LANG-1 §4.6). The two keys a
    // neighbour is compared by are the spec's reference string and one run of letters per word.
    $written = require dirname(__DIR__, 4).'/config/lesson/lang/fr.php';
    expect(array_keys(array_filter($written, static fn (mixed $value): bool => $value === null)))->toBe([])
        ->and($written['script_letters'])->toBe('/^[\p{Latin}]$/u')
        ->and(array_values(array_filter($written['common_words'], static fn (string $w): bool => preg_match('/^\p{L}+$/u', $w) !== 1 || $w !== mb_strtolower($w))))->toBe([]);
})->with(['fr']);

// Canon CHECK-1 on French (pack-keys §3.3–3.4): a sentence ends at . ? ! …, read through the space French puts before
// « ? ! » and inside the French quotes « … »; the dot of an abbreviation ends none inside a text and closes it at its very
// end. Lines of the scouting days (the last ones with an abbreviation the model writes, «Dr.», «M.», «etc.», put into a
// real line). CATCHES a question with its French space read as no question (the fatal `exchange.second_question` then
// blind), «avec le Dr. Lee» cut in two, «etc.» at the end read as an unclosed text, and a filler «M. Dupont» read as a
// sentence of its own (a fatal `filler.ungrammatical`).
it('ends a French sentence where French ends it — through the space before the mark, never at the dot of an abbreviation', function () {
    $ends = lessonPacks()->for('fr')->sentenceEnds();

    expect($ends->count("D'accord. C'est pour quel problème ?"))->toBe(2)
        ->and($ends->terminalKind("D'accord. C'est pour quel problème ?"))->toBe('question')
        ->and($ends->count('C\'est noté. Rendez-vous demain à dix heures. Arrivez dix minutes avant.'))->toBe(3)
        ->and($ends->terminal('C\'est noté. Rendez-vous demain à dix heures. Arrivez dix minutes avant.'))->toBe('.')
        ->and($ends->count('Bien sûr. Quelle est la raison de votre visite ?'))->toBe(2)
        ->and($ends->terminalKind('Il faut quoi comme informations ?'))->toBe('question')
        ->and($ends->terminalKind("Merci, à tout à l'heure !"))->toBe('exclamation')
        ->and($ends->count("Vous êtes inscrit pour 15 h aujourd'hui avec le Dr Lee."))->toBe(1)
        ->and($ends->terminal('La réceptionniste dit : « Arrivez dix minutes avant. »'))->toBe('.')
        ->and($ends->count('La réceptionniste dit : « Arrivez dix minutes avant. »'))->toBe(1)
        // The abbreviations a model writes.
        ->and($ends->count("Vous êtes inscrit pour 15 h aujourd'hui avec le Dr. Lee."))->toBe(1)
        ->and($ends->count('Bonjour M. Dupont, votre rendez-vous est demain à dix heures.'))->toBe(1)
        ->and($ends->count('Apportez votre carte Vitale, votre ordonnance, etc.'))->toBe(1)
        ->and($ends->closesText('Apportez votre carte Vitale, votre ordonnance, etc.'))->toBeTrue()
        ->and($ends->carriesSentence('M. Dupont'))->toBeFalse()
        ->and($ends->carriesSentence('Demain à dix heures.'))->toBeTrue();
});

/**
 * The frames of the ru→fr scouting day (scene «Запись к врачу»), as the model's raw answer writes them — «J'ai ___» with
 * no end mark, «Il faut quoi comme ___ ?» with the French space.
 *
 * @return list<ConversationPhrase>
 */
function frDayFrames(): array
{
    $frames = [];
    foreach ([
        'p1' => 'Je voudrais ___.', 'p2' => "J'ai ___", 'p3' => 'Depuis ___.', 'p4' => "___, c'est bon.",
        'p5' => 'Il faut quoi comme ___ ?', 'p6' => "Mon numéro, c'est ___.", 'p7' => "J'arrive ___.",
    ] as $ref => $frame) {
        $frames[] = new ConversationPhrase('s1', $ref, $frame, '', null, null);
    }

    return $frames;
}

// Canon FIX-4 §2 + LANG-1 §1 on the frames of the ru→fr scouting day: each learner line of the day says its own frame and
// the value in its window — the elisions spelt out («j'ai» is «je ai», «c'est» «ce est», «d'accord» an opening word), the
// article left out of the comparison and kept in the value; a negated line — «ne … pas», the elided «n'» too, and the
// spoken «pas» alone — says the same frame; the day's own simplified variant «Ça fait trois jours.» is almost «Depuis ___.»
// and nothing else. CATCHES an elision read as one foreign word («jai», «darrive»), the negation of French counted as two
// words added (the construction only almost said), «D'accord» — or the spoken «en fait», «super» — not read as an opening
// word (the first cut of the pack left «Euh, en fait, j'ai mal à la gorge» saying nothing), and a judge that credits a
// construction nobody said.
it('judges the learner lines of the ru→fr day against their frames', function () {
    $fr = lessonPacks()->for('fr');
    $judge = static fn (string $heard): MoveVerdict => (new FrameJudge)->move($heard, frDayFrames(), $fr);

    // The day's own lines — said, the value as the learner said it.
    expect($judge('Je voudrais un rendez-vous.')->said)->toBe(['s1:p1'])
        ->and($judge('Je voudrais un rendez-vous.')->values)->toBe(['s1:p1' => 'un rendez-vous'])
        ->and($judge("J'ai mal à la gorge.")->values)->toBe(['s1:p2' => 'mal à la gorge'])
        ->and($judge('Depuis trois jours.')->values)->toBe(['s1:p3' => 'trois jours'])
        ->and($judge("À dix heures, c'est bon.")->values)->toBe(['s1:p4' => 'À dix heures'])
        ->and($judge('Il faut quoi comme informations ?')->values)->toBe(['s1:p5' => 'informations'])
        ->and($judge("Mon numéro, c'est 06 12 34 56 78.")->values)->toBe(['s1:p6' => '06 12 34 56 78'])
        ->and($judge("D'accord, j'arrive dix minutes avant.")->values)->toBe(['s1:p7' => 'dix minutes avant'])
        ->and($judge('D’accord, j’arrive dix minutes avant.')->said)->toBe(['s1:p7'])
        // A negated line says the same construction: «ne» and «pas» anywhere, the elided «n'» spelt out, the spoken «pas».
        ->and($judge("Je n'ai pas mal à la gorge.")->values)->toBe(['s1:p2' => 'pas mal à la gorge'])
        ->and($judge("Demain matin, ce n'est pas bon.")->values)->toBe(['s1:p4' => 'Demain matin'])
        ->and($judge('Je ne voudrais pas un autre horaire.')->said)->toBe(['s1:p1'])
        ->and($judge("J'ai pas de fièvre.")->said)->toBe(['s1:p2'])
        // «au», «des» are a preposition and an article; opening words and a conjunction that opens a clause.
        ->and((new FrameJudge)->move("J'ai mal au ventre.", [new ConversationPhrase('x', 'p1', "J'ai mal à ___.", '', null, null)], $fr)->values)->toBe(['x:p1' => 'ventre'])
        ->and((new FrameJudge)->move("J'ai besoin des résultats.", [new ConversationPhrase('x', 'p1', "J'ai besoin de ___.", '', null, null)], $fr)->said)->toBe(['x:p1'])
        ->and($judge('Bonjour madame, je voudrais un rendez-vous.')->said)->toBe(['s1:p1'])
        ->and($judge("J'ai mal à la gorge et j'arrive dix minutes avant.")->said)->toBe(['s1:p2', 's1:p7'])
        // The openers of spoken French: «en fait», «super» before the construction.
        ->and($judge("Euh, en fait, j'ai mal à la gorge.")->values)->toBe(['s1:p2' => 'mal à la gorge'])
        ->and($judge("D'accord, super, j'arrive à dix heures.")->values)->toBe(['s1:p7' => 'à dix heures'])
        // The model's own simplified variant of B3 leaves out «depuis»: almost, never said — and no other frame.
        ->and($judge('Ça fait trois jours.'))->toEqual(new MoveVerdict([], ['s1:p3']))
        // A positive move against a negative frame is at most almost: an absent «ne … pas» is a difference like any other.
        ->and((new FrameJudge)->move("J'ai de la fièvre.", [new ConversationPhrase('x', 'p1', "Je n'ai pas ___.", '', null, null)], $fr)->said)->toBe([])
        // A move that stops on an article or a preposition broke off — it was not misunderstood.
        ->and((new FrameJudge)->breaksOff("J'ai mal à la", frDayFrames(), $fr))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Je voudrais prendre rendez-vous chez', frDayFrames(), $fr))->toBeTrue()
        ->and((new FrameJudge)->breaksOff("J'ai mal à la gorge", frDayFrames(), $fr))->toBeFalse();
});

// Canon FIX-3 §4 + LANG-1 §4 on French: the words of one number are one number in digits, with the pack's own lists
// (`speech()`: the canonical form of the text — a hyphen is a space). Lines of the scouting days — the times of the ru→fr
// receptionist, the phone number as the model read it out — and the numbers French counts by twenties. CATCHES
// «soixante-dix-huit» read as «60 10 8», «quatre-vingt-dix-neuf» as «4 20 10 9», «vingt et un» left «20 et un», «deux
// cents» read as «2 100», and «un» / «une» turned into «1» in «un rendez-vous» / «une place».
it('reads a French number said in words as the number in digits', function () {
    $speech = lessonPacks()->for('fr')->speech();
    $fold = static fn (string $text): string => implode(' ', SpokenNumbers::fold(
        explode(' ', (new LexicalNormalizer)->canonicalize($text)),
        $speech->numberWords,
        $speech->articles,
        $speech->numberJoiners,
        $speech->numberTensJoiners,
    ));

    expect($fold('Depuis trois jours.'))->toBe('depuis 3 jours')
        ->and($fold('Nous avons une place demain à dix heures.'))->toBe('nous avons une place demain à 10 heures')
        ->and($fold('Arrivez dix minutes avant.'))->toBe('arrivez 10 minutes avant')
        ->and($fold('Zéro six, douze, trente-quatre, cinquante-six, soixante-dix-huit.'))->toBe('0 6 12 34 56 78')
        ->and($fold('Zéro sept, quarante-cinq, onze, vingt-deux, trente.'))->toBe('0 7 45 11 22 30')
        ->and($fold('Je voudrais un rendez-vous.'))->toBe('je voudrais un rendez vous')
        ->and($fold('Le vingt et un mars, à vingt et une heures.'))->toBe('le 21 mars à 21 heures')
        ->and($fold('Ça fait soixante et onze euros, ou quatre-vingt-dix-neuf avec la radio.'))->toBe('ça fait 71 euros ou 99 avec la radio')
        ->and($fold('quatre-vingts, quatre-vingt-un, quatre-vingt-quatre'))->toBe('80 81 84')
        ->and($fold('La consultation coûte deux cents euros.'))->toBe('la consultation coûte 200 euros')
        ->and($fold('en mille neuf cent quatre-vingt-dix'))->toBe('en 1990')
        ->and($fold('un million'))->toBe('1000000')
        ->and($fold('entre vingt et trente minutes'))->toBe('entre 20 et 30 minutes')
        ->and($fold('entre cent et deux cents euros'))->toBe('entre 100 et 200 euros');
});

// Canon (LANG-1 §5, the guard of the translation) under a French learner: ordinary French lines of the fr→en scouting day
// (`text_native` of the receptionist and the patient) are translations — even the ones with one frequent French word or
// none («15 h me convient.») — and a line in a Latin neighbour is not. The neighbours are the deployed packs: a
// neighbour whose pack does not write its frequent words yet is told apart by nobody, and its row waits for it. CATCHES a
// French list that holds a word ordinary in a neighbour, and a neighbour's list that holds a word ordinary in French
// («de», «la», «est», «pour», «merci») — both make these lines «foreign».
it('takes an ordinary French line for a translation and a neighbour\'s line for none', function () {
    $fr = lessonPacks()->for('fr');

    foreach ([
        ['Of course. What is the reason for your visit?', 'Bien sûr. Quelle est la raison de votre visite ?'],
        ['Is it for a sore throat or something else?', "C'est pour un mal de gorge ou autre chose ?"],
        ['How long have you had the fever?', 'Depuis combien de temps avez-vous de la fièvre ?'],
        ['We have 10 a.m. and 3 p.m. today.', "Nous avons 10 h et 15 h aujourd'hui."],
        ['Okay, I can book you for 3 p.m. today.', "D'accord, je peux vous réserver 15 h aujourd'hui."],
        ["You're booked for 3 p.m. today with Dr. Lee.", "Vous êtes inscrit pour 15 h aujourd'hui avec le Dr Lee."],
        ['Thank you, see you then.', "Merci, à tout à l'heure."],
        ['3 p.m. works for me.', '15 h me convient.'],
    ] as [$target, $native]) {
        expect(ReplyNative::missing($target, $native, $fr))->toBeFalse("«{$native}» is French");
    }

    $refused = [
        'en' => ['What is your phone number?', 'Can I have your full name, please?'],
        'es' => ['What times are available today?', '¿Qué horarios tiene hoy? Puedo ir mañana por la tarde.'],
        'it' => ['What times are available today?', 'Vorrei un appuntamento per oggi, se possibile. Posso venire anche domani.'],
        'de' => ['What times are available today?', 'Haben Sie heute noch einen Termin frei? Ich kann auch morgen kommen.'],
    ];
    $checked = 0;
    foreach ($refused as $code => [$target, $native]) {
        if (lessonPacks()->for($code)->commonWords() === []) {
            continue;
        }
        $checked++;
        expect(ReplyNative::missing($target, $native, $fr))->toBeTrue("«{$native}» is {$code}, not French");
    }
    expect($checked)->toBeGreaterThanOrEqual(2);
});

// Canon (LANG-1 §5, the main session's update: «частые и отличительные»): a French frequent word that a Latin neighbour
// spells as an ordinary word of its own — however rare there — is in no French list, and no neighbour's service word is
// among them. CATCHES a homograph slipping back in: ro «ou» (the egg), «dans» (the dance), pl «moi» (my), ro «moi»
// (soft), de «Elle» (the ulna), it «merci» (goods), «mais» (maize), pl «je», «pas», «ma», ro «au», «ai», «va», it/ro
// «est», es «sur», «mes», de «du», «des», en «on», «pour», «chose» — each makes an honest line of that learner «French»,
// and the neighbours' lines under it with it: «Moi rodzice są w domu», «Vreau un ou fiert și pâine».
it('writes no French frequent word that a Latin neighbour says as its own', function () {
    $fr = lessonPacks()->for('fr');
    $homographs = ['ou', 'dans', 'moi', 'elle', 'mon', 'de', 'la', 'le', 'un', 'en', 'a', 'les', 'me', 'se', 'que', 'y',
        'je', 'pas', 'tu', 'il', 'ce', 'ne', 'non', 'qui', 'lui', 'quel', 'quelle', 'ma', 'est', 'ai', 'au', 'va', 'du', 'des',
        'on', 'pour', 'comment', 'plus', 'encore', 'chose', 'bien', 'sur', 'mes', 'son', 'nos', 'vos', 'mais', 'merci', 'trop',
        'cela', 'l', 'd', 'n', 's', 'c'];

    expect(array_values(array_intersect($fr->commonWords(), $homographs)))->toBe([]);

    $read = 0;
    foreach (lessonPacks()->codes() as $code) {
        $neighbour = lessonPacks()->for($code);
        if ($code === 'fr' || $neighbour->asNeighbour()['script_letters'] !== '/^[\p{Latin}]$/u' || ! $neighbour->has('function_words')) {
            continue;
        }
        $read++;
        expect(array_values(array_intersect($fr->commonWords(), array_diff($neighbour->words('function_words'), $neighbour->commonWords()))))
            ->toBe([], "a French frequent word is a service word of {$code}")
            ->and(array_values(array_intersect($neighbour->commonWords(), array_diff($fr->words('function_words'), $fr->commonWords()))))
            ->toBe([], "a frequent word of {$code} is a French service word");
    }
    expect($read)->toBeGreaterThanOrEqual(2);

    // The neighbours' honest lines that hold such a word stay theirs.
    foreach (['pl' => 'Moi rodzice są w domu.', 'ro' => 'Vreau un ou fiert și pâine.', 'de' => 'Zwei Karten à 15 Euro, bitte.'] as $code => $line) {
        expect(ReplyNative::missing('—', $line, lessonPacks()->for($code)))->toBeFalse("«{$line}» is {$code}");
    }
});

// Canon (LANG-1 §6, `talk_title_template`): a French learner's talk names its roles in no case, lower-cased but an
// acronym, joined by «et», the no-break space before the colon; no role at all — «Conversation». CATCHES the English
// fallback («Talk to the réceptionniste and the médecin»), an acronym lower-cased («iRM»), and a colon glued to the word.
it('titles a talk for a French learner', function () {
    $fr = lessonPacks()->for('fr');
    $strings = new NativeStrings('fr');

    expect($strings->talkTitle(['Réceptionniste', 'Médecin'], $fr))->toBe("Conversation\u{00A0}: réceptionniste et médecin")
        ->and($strings->talkTitle(['Réceptionniste', 'IRM', 'Médecin'], $fr))->toBe("Conversation\u{00A0}: réceptionniste, IRM et médecin")
        ->and($strings->talkTitle([], $fr))->toBe('Conversation');
});

// The learner's-language keys on the fr→en scouting day. CATCHES a gendered «je suis désolé» missed or «je suis ici» taken
// for one, the amount of «Поймай число» cut from its preposition, a Cyrillic reading of a French learner let through — and
// the false alarm of the first cut of the pack: a first name after «je suis» read as a gendered participle («Je suis Marie
// Dupont»).
it('reads the learner\'s keys on French lines', function () {
    $fr = lessonPacks()->for('fr');
    $words = new LanguageWords($fr);
    $values = NumberValues::of($fr);

    expect($words->genderedPast('Je suis désolé, je suis arrivée en retard.'))->toBe(['désolé', 'arrivée'])
        ->and($words->genderedPast('Je suis ici depuis trois jours, je suis aussi fatigué.'))->toBe(['fatigué'])
        ->and($words->genderedPast("J'ai mal à la gorge depuis trois jours."))->toBe([])
        // A first name after «je suis» is no participle: the line is read lower-cased, «marie» ends like «mariée».
        ->and($words->genderedPast('Bonjour, je suis Marie Dupont, j\'ai rendez-vous à 15 h.'))->toBe([])
        ->and($words->genderedPast('Je suis Louis Martin.'))->toBe([])
        ->and($words->genderedPast('Je me suis levé tôt, je ne suis pas allée travailler.'))->toBe(['levé', 'allée'])
        ->and($values?->value('Depuis combien de temps ? Depuis trois jours.'))->toBe(['text' => 'Depuis trois jours', 'number' => true])
        ->and($values?->value("J'ai de la fièvre depuis plus d'une semaine."))->toBe(['text' => "Depuis plus d'une semaine", 'number' => false])
        ->and($words->isTime("aujourd'hui"))->toBeTrue()
        ->and($words->isNumber('quatre-vingt-dix'))->toBeTrue()
        ->and($words->isNumber('une'))->toBeFalse()
        ->and($words->foreignLetters('айд лайк ту мейк эн эпойнтмэнт'))->not->toBe([])
        ->and($words->foreignLetters('aïde laïke tou méïke'))->toBe([]);
});
