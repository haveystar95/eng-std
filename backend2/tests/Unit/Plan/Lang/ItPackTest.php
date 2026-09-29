<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Assembly\NumberValues;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
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
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\Service\SpokenNumbers;

/**
 * THE ITALIAN PACK (наряд LANG-1, `config/lesson/lang/it.php`) — Italian is taught and spoken, so the pack is read from
 * both sides: as the target of ru→it, as the learner's own language of it→en. The lines below are the scouting run's own
 * (`docs/research/lang-1/days/it-en.md`, `ru-it.md`): the Italian of it→en's native side and of ru→it's partner — ru→it's
 * learner side came out in English (the model's slip, na-glaz.md), so the frames the judge is asked about are it→en's
 * native frames, the Italian the lesson drills.
 */

// Canon (pack-keys §7.2, the order's pairs ru→it and it→en): every reader of both sides takes the pack in the shape it
// reads. CATCHES a key left null or missing, and one written in a shape its reader throws on.
it('gives every reader of the pack what it reads, in the shape it reads it', function (string $code) {
    $pack = lessonPacks()->for($code);
    $words = new LanguageWords($pack);

    expect(LanguageRoles::planTargets())->toContain($code)
        ->and(LanguageRoles::planNatives())->toContain($code);

    foreach (['abbreviations', 'question_word_order', 'unstressed_words', 'number_words', 'number_joiners', 'irregular_forms', 'inflection_rules', 'person_swap', 'contractions', 'contractions_before', 'intro_words', 'clause_starters', 'negation', 'partitive', 'dangling_words', 'rescue_line', 'neutral_reply', 'script_letters', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the target side reads `{$key}`");
    }
    expect(NumberValues::of($pack))->not->toBeNull();
    (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
    (new FrameJudge)->breaksOff('a b', [], $pack);
    (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
    WordBases::of('abc', $pack);
    $words->isQuestion('a b');

    foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
    }
    expect($pack->talkTitleTemplate())->not->toBeNull()
        ->and((new NativeStrings($code))->talkTitle(['Recepcjonistka', 'MRI'], $pack))->toContain('MRI');
    $words->genderedPast('a b');
    $words->foreignLetters('ab');

    // No key is null: null is «not written», and the rule that needs it does not run (the order's rule).
    $written = require dirname(__DIR__, 4).'/config/lesson/lang/it.php';
    expect(array_keys(array_filter($written, static fn (mixed $value): bool => $value === null)))->toBe([])
        ->and($pack->rescueLine())->toBe('Scusi?')
        ->and($pack->neutralReply())->toBe('Capisco. Continui, per favore.');
})->with(['it']);

// Canon (наряд CHECK-1, LANG-1 §1): a sentence ends at . ? ! … — not at the dot of an Italian abbreviation («dott.»,
// «sig.ra», «ecc.», «S.p.A.», «min.»), which closes a text only at its very end; the Italian guillemets close a sentence
// too. Five partner lines of the scouting days, then lines with the abbreviations. CATCHES «Il dott. Rossi…» counted as
// two sentences, a filler «…, un documento ecc.» or «alla Rossi S.p.A.» read as carrying a sentence of its own (fatal
// `filler.ungrammatical`), and a question with an abbreviation in it losing its question.
it('ends Italian sentences where they end, never at an abbreviation\'s dot', function () {
    $ends = lessonPacks()->for('it')->sentenceEnds();

    expect($ends)->not->toBeNull();
    assert($ends !== null);
    expect($ends->count('Certo. Qual è il problema?'))->toBe(2)
        ->and($ends->terminalKind('Certo. Qual è il problema?'))->toBe('question')
        ->and($ends->count('Abbiamo posto oggi alle tre o domani alle nove.'))->toBe(1)
        ->and($ends->count('Prima l\'accettazione. Poi aspetta il medico.'))->toBe(2)
        ->and($ends->count('Per favore, arrivi dieci minuti prima per la registrazione.'))->toBe(1)
        ->and($ends->terminalKind('Sì, quell\'orario è ancora libero.'))->toBe('statement')
        // Abbreviations: a dot inside ends nothing; at the very end it closes the text, and a filler ending so carries no
        // sentence of its own.
        ->and($ends->count('Il dott. Rossi la aspetta domani alle 9.'))->toBe(1)
        ->and($ends->count('Il dr. Bianchi arriva tra 10 min. circa.'))->toBe(1)
        ->and($ends->count('La sig.ra Bianchi è già qui?'))->toBe(1)
        ->and($ends->terminalKind('La sig.ra Bianchi è già qui?'))->toBe('question')
        ->and($ends->count('Porti la tessera sanitaria, un documento ecc. e venga alle 9.'))->toBe(1)
        ->and($ends->closesText('Porti la tessera sanitaria, un documento ecc.'))->toBeTrue()
        ->and($ends->carriesSentence('la tessera sanitaria, un documento ecc.'))->toBeFalse()
        ->and($ends->carriesSentence('alla Rossi S.p.A.'))->toBeFalse()
        ->and($ends->carriesSentence('tra 10 min.'))->toBeFalse()
        ->and($ends->carriesSentence('Arrivo domani.'))->toBeTrue()
        // The Italian quotes: «Va bene.» ends where its guillemet closes.
        ->and($ends->count('«Va bene.» Poi arrivo alle 3.'))->toBe(2);
});

// Canon (наряд FIX-4 §2, LANG-1 §1): a frame is said as a coherent phrase; «non» is free anywhere (the negation of the
// pack); an elision is its two words; one difference is «almost». The seven native frames of it→en's day, the lines the
// day says with them. CATCHES «Non ho mal di gola» counted as a word added (only almost «Ho ___»), «mal d'orecchio»
// broken at its apostrophe, «Arrivo» for «Arriverò» called said, and a construction after «e» missed.
it('judges the day\'s Italian lines against its frames: said, said in the negative, almost', function () {
    $pack = lessonPacks()->for('it');
    $frame = static fn (string $ref, string $text): ConversationPhrase => new ConversationPhrase('s1', $ref, $text, '', null, null);
    $one = static fn (string $heard, string $text, ?LanguagePack $with = null): MoveVerdict => (new FrameJudge)->move($heard, [$frame('p', $text)], $with ?? $pack);

    // The day's own lines (B1, B2, B3, B5, B6, B8) against their frames — said, the window as the learner said it.
    expect($one('Ho bisogno di un appuntamento dal medico.', 'Ho bisogno di ___.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'un appuntamento dal medico']))
        ->and($one('Ho mal di gola e la febbre.', 'Ho ___.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'mal di gola e la febbre']))
        ->and($one('Li ho da tre giorni.', 'Li ho ___.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'da tre giorni']))
        ->and($one('Posso prendere le 3 di domani?', 'Posso prendere ___?'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'le 3 di domani']))
        ->and($one('Arriverò dieci minuti prima.', 'Arriverò ___.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'dieci minuti prima']))
        ->and($one('Domani alle 3 va bene.', '___ va bene.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'Domani alle 3']))
        // Said in the negative: «non» is no difference, wherever it stands; «No,» «Mi scusi,» «Ehm,» open the move.
        ->and($one('Non ho mal di gola.', 'Ho ___.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'mal di gola']))
        ->and($one('No, non ho bisogno di un appuntamento.', 'Ho bisogno di ___.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'un appuntamento']))
        ->and($one('Mi scusi, posso prendere le 10 di oggi?', 'Posso prendere ___?'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'le 10 di oggi']))
        ->and($one('Ehm, arriverò puntuale.', 'Arriverò ___.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'puntuale']))
        // An elision is its two words: the filler p2.f3 «mal d'orecchio» is the window whole.
        ->and($one('Ho mal d\'orecchio.', 'Ho ___.'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'mal d\'orecchio']))
        // Almost: another tense of the verb, and «l'» (lo) for «li».
        ->and($one('Arrivo dieci minuti prima.', 'Arriverò ___.'))->toEqual(new MoveVerdict([], ['s1:p']))
        ->and($one('L\'ho da tre giorni.', 'Li ho ___.'))->toEqual(new MoveVerdict([], ['s1:p']));

    // A construction after «e» begins a clause: two of the day's frames in one sentence, each with its own window.
    expect((new FrameJudge)->move('Ho la febbre e li ho da tre giorni.', [$frame('p2', 'Ho ___.'), $frame('p3', 'Li ho ___.')], $pack))
        ->toEqual(new MoveVerdict(['s1:p2', 's1:p3'], [], ['s1:p2' => 'la febbre', 's1:p3' => 'da tre giorni']));

    // Without the pack's negation and elisions the same moves are only almost said — what the two keys buy: «quant'è» is
    // one word «quantè» then, and the learner's «Quanto è» one difference from it.
    $bare = new LanguagePack('it', ['contractions' => [], 'negation' => []] + (require dirname(__DIR__, 4).'/config/lesson/lang/it.php'));
    expect($one('Non ho mal di gola.', 'Ho ___.', $bare))->toEqual(new MoveVerdict([], ['s1:p']))
        ->and($one('Quanto è la visita?', 'Quant\'è ___?'))->toEqual(new MoveVerdict(['s1:p'], [], ['s1:p' => 'la visita']))
        ->and($one('Quanto è la visita?', 'Quant\'è ___?', $bare))->toEqual(new MoveVerdict([], ['s1:p']));

    // A move cut off on a preposition, an elided one or a conjunction broke off; one that ends on «una» («Ne ho una») did
    // not.
    expect((new FrameJudge)->breaksOff('Ho bisogno di', [], $pack))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Ho bisogno dell\'', [], $pack))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Ho mal di gola e', [], $pack))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Ne ho una', [], $pack))->toBeFalse()
        ->and((new FrameJudge)->breaksOff('Ho mal di gola e la febbre', [], $pack))->toBeFalse();
});

// Canon (DECISIONS п. 393, LANG-1 §4): the words of one number are that number — 0–20, the tens, the compounds written
// as one word (ventuno, ventun, trentotto, ventitré with and without its accent), the hundreds, the thousands; «un»
// before a scale is 1; no joiner in Italian («e» stays a word). Real lines of the days first. CATCHES a compound left a
// word (a recogniser's «23» failing «ventitré»), «ventotto» spelled «ventiotto», «un caffè» read as «1 caffè», and «e»
// joining «uno e due» into 3.
it('reads Italian numbers said in words as their digits', function () {
    $speech = lessonPacks()->for('it')->speech();
    $fold = static fn (string $text): string => implode(' ', SpokenNumbers::fold(
        explode(' ', (new LexicalNormalizer)->canonicalize($text)),
        $speech->numberWords, $speech->articles, $speech->numberJoiners, $speech->numberTensJoiners,
    ));

    expect($fold('Venga quindici minuti prima.'))->toBe('venga 15 minuti prima')
        ->and($fold('Abbiamo posto oggi alle tre o domani alle nove.'))->toBe('abbiamo posto oggi alle 3 o domani alle 9')
        ->and($fold('Arriverò dieci minuti prima.'))->toBe('arriverò 10 minuti prima')
        ->and($fold('Li ho da tre giorni.'))->toBe('li ho da 3 giorni')
        ->and($fold('Ho trentotto e mezzo di febbre da ventitré ore'))->toBe('ho 38 e mezzo di febbre da 23 ore')
        ->and($fold('ventitre ventuno ventun ventotto novantanove'))->toBe('23 21 21 28 99')
        ->and($fold('cento venti euro, duemila euro, due mila euro, un milione'))->toBe('120 euro 2000 euro 2000 euro 1000000')
        ->and($fold('le venti e cinque'))->toBe('le 20 e 5')
        ->and($fold('un caffè e uno e due'))->toBe('un caffè e 1 e 2');

    // What a card compares: a recogniser's digits against the lesson's words; the indefinite article is eaten as en «a».
    expect((new SpeechMatch)->repeated('venga 15 minuti prima', 'Venga quindici minuti prima.', $speech))->toBeTrue()
        ->and((new SpeechMatch)->repeated('ho 38 di febbre', 'Ho trentotto di febbre.', $speech))->toBeTrue()
        ->and((new SpeechMatch)->repeated('ho 21 anni', 'Ho ventun anni.', $speech))->toBeTrue()
        ->and((new SpeechMatch)->repeated('vorrei caffè', 'Vorrei un caffè.', $speech))->toBeTrue()
        ->and((new SpeechMatch)->repeated('ho la febbre', 'Non ho la febbre.', $speech))->toBeFalse();
});

// Canon (FIX-4c §6, LANG-1 §5): an ordinary Italian line of the role is a translation with the deployed packs and their
// neighbours; a line in another language of the Latin letters is not. The it→en day's own lines on both sides, and one
// Italian line under a Polish learner. CATCHES a `common_words` of Italian that holds a word another Latin language
// says as often (the list is «частые и отличительные»), which makes an honest line of that language «Italian», and a
// list too thin to tell Italian at all.
it('keeps ordinary Italian lines a translation and refuses a neighbour\'s line in the same letters', function () {
    $it = lessonPacks()->for('it');
    $refused = array_values(array_filter([
        'Certo. Qual è il problema?',
        'Può dirmi brevemente i sintomi?',
        'Da quanto tempo ha questi sintomi?',
        'Sì, quell\'orario è ancora libero.',
        'Per favore, venga dieci minuti prima del suo appuntamento.',
        'Abbiamo le 10 di oggi o le 3 di domani.',
        'Mi dica pure.',
        'Da quanti giorni?',
        'Prima fa l\'accettazione, poi aspetta il medico.',
        'Porti la tessera sanitaria e un documento.',
    ], static fn (string $line): bool => ReplyNative::missing('—', $line, $it)));

    expect($refused)->toBe([])
        ->and(ReplyNative::missing('—', 'How long have you had these symptoms?', $it))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Please arrive ten minutes early for registration.', $it))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Da quanto tempo ha questi sintomi?', lessonPacks()->for('pl')))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Buongiorno, mi dica: di che cosa ha bisogno?', lessonPacks()->for('es')))->toBeTrue();

    foreach ($it->commonWords() as $word) {
        expect(preg_match('/^[\p{L}\p{M}]+$/u', $word))->toBe(1, "«{$word}» is one run of letters");
    }
});

// Canon (LANG-1 §5, the main session's update: «частые и отличительные»): an Italian word that another Latin language of
// the plan spells alike — however rare there — is in no Italian list of frequent words: those the pack's comment names.
// CATCHES a homograph slipping back in — fr «perché» (perched), ro «ancora» (the anchor), pl «dal» (the distance), es «di»
// (I gave), «prima» (cousin), «loro» (parrot), ro «mai» (more), «ora» (the hour), «noi», «voi», fr «qui», «lui», en «dove»,
// «come», «pure».
it('writes no Italian word that a Latin neighbour spells as an ordinary word of its own', function () {
    $homographs = ['di', 'e', 'a', 'la', 'il', 'un', 'non', 'per', 'con', 'da', 'mi', 'ma', 'se', 'si', 'come', 'dove',
        'cosa', 'solo', 'prima', 'una', 'uno', 'del', 'lei', 'noi', 'voi', 'mai', 'ora', 'mia', 'ancora', 'qui', 'lui', 'ne',
        'va', 'perché', 'dal', 'loro', 'pure', 'venga', 'alle', 'al', 'in', 'i', 'o', 'le'];

    expect(array_values(array_intersect(lessonPacks()->for('it')->commonWords(), $homographs)))->toBe([]);
});

// Canon (LANG-1 §5, the main session's update: «частые и отличительные»): no Italian frequent word is a word a Latin
// neighbour's pack calls its own service word, and no neighbour's frequent word is an Italian service word — the words
// both languages say go to neither list. CATCHES «per», «con», «la», «non» in the Italian list (an honest Spanish, French
// or English line would count them as Italian), and a neighbour's list holding an Italian word.
it('writes no frequent word that a Latin neighbour says as its own, and meets none of theirs among its own', function () {
    $it = lessonPacks()->for('it');
    $clashes = [];
    $read = 0;
    foreach (lessonPacks()->codes() as $other) {
        $neighbour = lessonPacks()->for($other);
        if ($other === 'it' || $neighbour->asNeighbour()['script_letters'] !== '/^[\p{Latin}]$/u' || ! $neighbour->has('function_words')) {
            continue;
        }
        $read++;
        foreach (array_intersect($it->commonWords(), array_diff($neighbour->words('function_words'), $neighbour->commonWords())) as $word) {
            $clashes[] = "it in {$other}: «{$word}»";
        }
        foreach (array_intersect($neighbour->commonWords(), array_diff($it->words('function_words'), $it->commonWords())) as $word) {
            $clashes[] = "{$other} in it: «{$word}»";
        }
    }
    if ($read === 0) {
        $this->markTestSkipped('no Latin neighbour of it writes its service words yet');
    }

    expect($clashes)->toBe([]);
});

// Canon (наряд LANG-1 §6): the talk's title in Italian names the roles as written, in no case — «Conversazione: …», «e»
// before the last role, «ed» before one that opens with «e», the first letter lower-cased unless an acronym. The it→en
// plan's own roles. CATCHES the English fallback («Talk to the addetto…») and an «e» where Italian writes «ed».
it('titles an Italian talk with its roles, uninflected', function () {
    $it = lessonPacks()->for('it');
    $strings = new NativeStrings('it');

    expect($strings->talkTitle(['Addetto alla reception', 'Medico'], $it))->toBe('Conversazione: addetto alla reception e medico')
        ->and($strings->talkTitle(['Medico', 'Endocrinologo'], $it))->toBe('Conversazione: medico ed endocrinologo')
        ->and($strings->talkTitle(['Medico'], $it))->toBe('Conversazione: medico')
        ->and($strings->talkTitle(['Addetto alla reception', 'Infermiera', 'RMN tecnico'], $it))->toBe('Conversazione: addetto alla reception, infermiera e RMN tecnico')
        ->and($strings->talkTitle([], $it))->toBe('Conversazione');
});

// Canon (TEXT QUALITY, READINGS — the learner's side): the it→en day's readings are in the learner's letters and none of
// its learner lines says the learner's gender; «sono stata», «sono arrivato», «sono allergica» do say the gender, «È
// prenotato» (the role's line about the learner, not the learner's own) and «I sintomi sono iniziati» do not; «uno» and
// «una» are no numbers, «ventitrè» is one. CATCHES a native check of Italian firing on the scouting day, an English reading
// with «ò» refused, and the Greek «θ» of an IPA reading let through.
it('reads the learner\'s Italian side of it→en as the canon says', function () {
    $words = new LanguageWords(lessonPacks()->for('it'));

    foreach (['Ho bisogno di un appuntamento dal medico.', 'Ho mal di gola e la febbre.', 'Li ho da tre giorni.', 'Arriverò dieci minuti prima.', 'Va bene, domani alle 3 va bene.', 'È prenotato per domani alle 3.', 'I sintomi sono iniziati ieri.', 'Ci sono sabato e domenica.', 'Sono subito da lei.', 'Sono in ritardo.'] as $line) {
        expect($words->genderedPast($line))->toBe([], $line);
    }
    expect($words->genderedPast('Sono stata male tutta la notte.'))->toBe(['stata'])
        ->and($words->genderedPast('Sono arrivato ieri sera.'))->toBe(['arrivato'])
        ->and($words->genderedPast('Mi sono fatto male alla schiena.'))->toBe(['fatto'])
        ->and($words->genderedPast('Sono un po\' stanca.'))->toBe(['stanca'])
        ->and($words->genderedPast('Sono allergica alla penicillina.'))->toBe(['allergica']);

    foreach (['ai niid a dottorz appòintment', 'ai hav a sor thròut end fìiver', 'ail aràiv ___', 'an ììreik', 'okèi, tomòrou at thrii pii em uorks'] as $reading) {
        expect($words->foreignLetters($reading))->toBe([], $reading);
    }
    expect($words->foreignLetters('ai hæv ə sor θroat'))->toBe(['θ']);

    // The numbers an option names: an article is none, «ventitrè» with its accent is one.
    expect($words->isNumber('uno'))->toBeFalse()
        ->and($words->isNumber('una'))->toBeFalse()
        ->and($words->isNumber('ventitrè'))->toBeTrue();
});

// Canon (наряд SESSION-1a, «Поймай число», BACK-TAILS-1 §1.3): the amount a line of the ru→it partner says, as an option
// shows it — with the preposition and the determiner that carry it. CATCHES «Giorni» out of «da tre giorni», «Settimana»
// out of «tra una settimana», «Nei prossimi tre giorni» cut to «Prossimi tre giorni».
it('offers the amount an Italian line says as the line says it', function () {
    $values = NumberValues::of(lessonPacks()->for('it'));
    assert($values !== null);

    expect($values->value('Venga quindici minuti prima.'))->toBe(['text' => 'Quindici minuti', 'number' => true])
        ->and($values->value('Abbiamo posto oggi alle tre o domani alle nove.'))->toBe(['text' => 'Alle tre', 'number' => true])
        ->and($values->value('Li ho da tre giorni.'))->toBe(['text' => 'Da tre giorni', 'number' => true])
        ->and($values->value('Torni tra una settimana.'))->toBe(['text' => 'Tra una settimana', 'number' => false])
        ->and($values->value('Prenda le compresse nei prossimi tre giorni.'))->toBe(['text' => 'Nei prossimi tre giorni', 'number' => true])
        ->and($values->value('Va bene, a domani.'))->toBeNull();
});
