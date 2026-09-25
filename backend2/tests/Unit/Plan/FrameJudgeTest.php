<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\MoveVerdict;

/**
 * THE JUDGE OF THE TALK'S CONSTRUCTIONS — A COHERENT PHRASE, NOT A BAG OF WORDS (наряд FIX-4 §2), on the frames of the
 * owner's gym rehearsal (plan 2DX8QC): «Ресепшен зала» and «С тренером», seven frames each — and (наряд LANG-1 §1) the
 * negation, elisions and opening marks of the six new targets, on packs built here in the shape the order fixes: the real packs
 * are the language executors' to write, and a row below must not change when they land.
 */

/**
 * A pack of the judge's keys only, each written as the no-op the key spec gives it, with `$keys` over them — a language
 * as a language executor writes it for the judge.
 *
 * @param  array<string, mixed>  $keys
 */
function fjPack(string $code, array $keys = []): LanguagePack
{
    return new LanguagePack($code, [
        'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],
        'articles' => [], 'contractions' => [], 'contractions_before' => [], 'intro_words' => [], 'clause_starters' => [],
        'negation' => [], 'partitive' => [], 'dangling_words' => [],
        ...$keys,
    ]);
}

/** One frame `p1` of a scene `x`, judged in a move under `$pack`. */
function fjOne(string $heard, string $frame, LanguagePack $pack): MoveVerdict
{
    return (new FrameJudge)->move($heard, [new ConversationPhrase('x', 'p1', $frame, '', null, null)], $pack);
}

/** @return list<ConversationPhrase> */
function fjReception(): array
{
    return fjFrames('s1', [
        'p1' => ['Do you have ___?', ExchangeKind::Ask], 'p2' => ['What ___ do you have?', ExchangeKind::Ask],
        'p3' => ['This is ___.', ExchangeKind::Answer], 'p4' => ['That works for me on ___.', ExchangeKind::Answer],
        'p5' => ['Where are ___?', ExchangeKind::Ask], 'p6' => ['I\'ll return ___.', ExchangeKind::Answer],
        'p7' => ['Can I pay ___?', ExchangeKind::Ask],
    ]);
}

/** @return list<ConversationPhrase> */
function fjTrainer(): array
{
    return fjFrames('s2', [
        'p1' => ['I\'m working on ___.', ExchangeKind::Answer], 'p2' => ['I have ___ of experience.', ExchangeKind::Answer],
        'p3' => ['I have some ___.', ExchangeKind::Answer], 'p4' => ['How do I use ___?', ExchangeKind::Ask],
        'p5' => ['How heavy should ___ be?', ExchangeKind::Ask], 'p6' => ['I\'ll rest for ___.', ExchangeKind::Answer],
        'p7' => ['Should I keep ___ down?', ExchangeKind::Ask],
    ]);
}

/**
 * @param  array<string, array{0: string, 1: ExchangeKind}>  $frames
 * @return list<ConversationPhrase>
 */
function fjFrames(string $scene, array $frames): array
{
    $out = [];
    foreach ($frames as $ref => [$frame, $kind]) {
        $out[] = new ConversationPhrase($scene, $ref, $frame, '', null, null, $kind);
    }

    return $out;
}

/** @param list<ConversationPhrase> $frames */
function fjJudge(string $heard, array $frames): MoveVerdict
{
    return (new FrameJudge)->move($heard, $frames, lessonPacks()->for('en'));
}

// Canon (§2, приёмка А — the rehearsal 01M36X3W…, move by move): the frame's part before the window is an unbroken run
// where the move begins (or after its opening words), the window has a word, the part after follows it; one difference
// of words is «almost». CATCHES the bag of words: «do you have» found inside another phrase, «work … for me» taken for
// «That works for me on ___», and «I have a shoulder pain» that is nothing at all.
it('judges the owner\'s rehearsal move by move as the canon says', function () {
    expect(fjJudge('Hello what kind of memberships do you have', fjReception()))
        ->toEqual(new MoveVerdict(['s1:p2'], [], ['s1:p2' => 'kind of memberships']))
        ->and(fjJudge('Yes it is my first visit', fjReception()))->toEqual(new MoveVerdict([], ['s1:p3']))
        ->and(fjJudge('Nice work days work for me', fjReception()))->toEqual(new MoveVerdict)
        ->and(fjJudge('I have about one year of experience', fjTrainer()))->toEqual(new MoveVerdict(['s2:p2'], [], ['s2:p2' => 'about one year']))
        ->and(fjJudge('Yes I have a shoulder pain', fjTrainer()))->toEqual(new MoveVerdict([], ['s2:p3']))
        ->and(fjJudge('OK thank you how do I use this machine', fjTrainer()))->toEqual(new MoveVerdict(['s2:p4'], [], ['s2:p4' => 'this machine']))
        ->and(fjJudge('OK thank you I will rest for 45 seconds', fjTrainer()))->toEqual(new MoveVerdict(['s2:p6'], [], ['s2:p6' => '45 seconds']))
        ->and(fjJudge('I have some shoulder pain', fjTrainer()))->toEqual(new MoveVerdict(['s2:p3'], [], ['s2:p3' => 'shoulder pain']))
        ->and(fjJudge('OK great', fjTrainer()))->toEqual(new MoveVerdict);
});

// Canon (§2, решение владельца 24.09 по п. 395): a negative is the same construction — «not» and «do not» inside the
// frame's words are no difference, the verb after «do» read by its base; «no» is in the window. CATCHES «I don't have»
// read as two words added, and «doesn't have» not taken for «has».
it('reads a construction said in the negative as the construction', function () {
    $fever = [new ConversationPhrase('d', 'p1', 'He has ___.', 'У него ___.', null, null)];

    expect(fjJudge('I don\'t have any experience', fjTrainer())->said)->toBe(['s2:p2'])
        ->and(fjJudge('no, he doesn\'t have a fever', $fever)->said)->toBe(['d:p1'])
        ->and(fjJudge('I have no experience', fjTrainer())->said)->toBe(['s2:p2'])
        ->and(fjJudge('I am not working on anything', fjTrainer())->said)->toBe(['s2:p1'])
        // One difference beyond the negation is almost; two are nothing.
        ->and(fjJudge('I don\'t have any shoulder pain', fjTrainer()))->toEqual(new MoveVerdict([], ['s2:p3']))
        ->and(fjJudge('we don\'t have much of experience', fjTrainer()))->toEqual(new MoveVerdict([], ['s2:p2']));
});

// Canon (§2): «I'm» is «I am», «he's been» is «he has been», articles take no part, marks split nothing; a form of a word
// is a difference — «almost». CATCHES a contraction read as another word, an article dropped or added in the frame's own
// words counted as a difference, and «work» for «works» called said.
it('spells contractions out, leaves articles out, and counts another form of a word as a difference', function () {
    $sick = [new ConversationPhrase('d', 'p2', 'He\'s been sick ___.', '', null, null)];

    expect(fjJudge('Pain is sharp when he bends', [new ConversationPhrase('d', 'p3', 'The pain is ___ when he bends.', '', null, null)])->said)->toBe(['d:p3'])
        ->and(fjJudge('He does not have the fever.', [new ConversationPhrase('d', 'p4', 'He doesn\'t have a fever.', '', null, null)])->said)->toBe(['d:p4'])
        ->and(fjJudge('I am working on general fitness', fjTrainer())->said)->toBe(['s2:p1'])
        ->and(fjJudge('Hi, I\'m working on the general fitness.', fjTrainer())->values)->toBe(['s2:p1' => 'the general fitness'])
        ->and(fjJudge('he has been sick for two days', $sick)->said)->toBe(['d:p2'])
        ->and(fjJudge('He\'s been sick for two days.', $sick)->said)->toBe(['d:p2'])
        ->and(fjJudge('That works for me on weekdays', fjReception())->said)->toBe(['s1:p4'])
        ->and(fjJudge('That work for me on weekdays', fjReception()))->toEqual(new MoveVerdict([], ['s1:p4']))
        ->and(fjJudge('Can I pay by card?', fjReception())->values)->toBe(['s1:p7' => 'by card']);
});

// Canon (§2): «префикс стоит в начале высказывания или сразу после вступительных слов» — a sentence of the move is an
// utterance of its own; the opening words are only the ones the move begins with; (FIX-4b §1) and a clause begins after
// a conjunction — «the machine and how do I use it» says «How do I use ___?» now (DECISIONS «Спорное» п. 3 named this very
// line). CATCHES a frame found in the middle of a sentence with no conjunction before it, and a frame of the second
// sentence missed.
it('finds a frame where a sentence begins, after its opening words or after a conjunction, and nowhere else', function () {
    expect(fjJudge('Hello. What kind of memberships do you have?', fjReception())->said)->toBe(['s1:p2'])
        ->and(fjJudge('the machine and how do I use it', fjTrainer()))->toEqual(new MoveVerdict(['s2:p4'], [], ['s2:p4' => 'it']))
        ->and(fjJudge('I\'m working on general fitness. I have about a year of experience.', fjTrainer())->said)->toBe(['s2:p1', 's2:p2'])
        ->and(fjJudge('where are the changing rooms', fjReception())->said)->toBe(['s1:p5'])
        ->and(fjJudge('the changing rooms where are', fjReception()))->toEqual(new MoveVerdict)
        // A window of no word is no construction: the move broke off where the window opens.
        ->and(fjJudge('I\'m working on', fjTrainer()))->toEqual(new MoveVerdict)
        ->and((new FrameJudge)->breaksOff('I\'m working on', fjTrainer(), lessonPacks()->for('en')))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('Yes my', fjTrainer(), lessonPacks()->for('en')))->toBeTrue()
        ->and((new FrameJudge)->breaksOff('I have a shoulder pain', fjTrainer(), lessonPacks()->for('en')))->toBeFalse();
});

// Canon (§2, «для ru/uk/ro — пустой»; FIX-4b §1, «ru/uk/ro — пусто, без находки»): a language whose pack writes no
// opening words, no conjunctions and no contractions is judged by the frame's own words alone. CATCHES English lists
// borrowed for another language.
it('forgives nothing in a language whose pack writes no opening words', function () {
    $ru = lessonPacks()->for('ru');
    $frame = [new ConversationPhrase('r', 'p1', 'Это ___.', '', null, null)];

    expect((new FrameJudge)->move('это мой первый визит', $frame, $ru)->said)->toBe(['r:p1'])
        ->and((new FrameJudge)->move('да это мой первый визит', $frame, $ru))->toEqual(new MoveVerdict([], ['r:p1']))
        ->and((new FrameJudge)->move('я здесь и это мой первый визит', $frame, $ru))->toEqual(new MoveVerdict([], ['r:p1']))
        // A language with no pack at all, and one whose judge's keys are the empty no-ops, the same (наряд LANG-1 §1).
        ->and((new FrameJudge)->move('да это мой первый визит', $frame, LanguagePack::none('xx')))->toEqual(new MoveVerdict([], ['r:p1']))
        ->and((new FrameJudge)->move('не это мой первый визит', $frame, fjPack('xx')))->toEqual(new MoveVerdict([], ['r:p1']));
});

// Canon (наряд FIX-4b §1): «префикс каркаса может стоять в середине высказывания сразу после союза из ключа пакета
// clause_starters — en: and, but, so, then, or; запятая перед союзом допустима», the order's four lines word for word —
// two constructions glued into one sentence are both said, a frame with no conjunction before it still is not. The window
// of the first construction ends before the conjunction of the second (its value is «my first visit», not the clause
// after it), and a window with no construction after its «and» keeps it («a fever and a sore throat»). CATCHES the second
// of two glued constructions lost (FIX-4: only a sentence's start counted), a conjunction read as an opening word anywhere
// («do you have» after «memberships»), and the first construction's value swallowing the second one.
it('reads a construction after a conjunction as the start of a clause', function () {
    $both = [...fjReception(), ...fjTrainer()];
    $fever = [new ConversationPhrase('d', 'p1', 'He has ___.', 'У него ___.', null, null)];
    $glued = new MoveVerdict(['s1:p3', 's2:p2'], [], ['s1:p3' => 'my first visit', 's2:p2' => 'about a year']);

    expect(fjJudge('Yes, this is my first visit and I have about a year of experience.', $both))->toEqual($glued)
        ->and(fjJudge('Yes, this is my first visit, and I have about a year of experience.', $both))->toEqual($glued)
        ->and(fjJudge('I have a fever and I have some shoulder pain.', fjTrainer()))->toEqual(new MoveVerdict(['s2:p3'], [], ['s2:p3' => 'shoulder pain']))
        ->and(fjJudge('Hello what kind of memberships do you have', fjReception()))->toEqual(new MoveVerdict(['s1:p2'], [], ['s1:p2' => 'kind of memberships']))
        ->and(fjJudge('Weekdays works for me', fjReception()))->toEqual(new MoveVerdict)
        // A conjunction opens a clause wherever it stands — at the start of a sentence too.
        ->and(fjJudge('But I have some shoulder pain', fjTrainer()))->toEqual(new MoveVerdict(['s2:p3'], [], ['s2:p3' => 'shoulder pain']))
        ->and(fjJudge('He has a fever and a sore throat.', $fever)->values)->toBe(['d:p1' => 'a fever and a sore throat']);
});

// Canon (наряд LANG-1 §1, the order's own lines): «слово из words, вставленное в ход, ничего не стоит — сразу после слова
// из after, когда after — список; ГДЕ УГОДНО (включая первое слово), когда after — null»: pl «Nie mam gorączki» is «Mam
// ___», ro «Nu am febră» (and «N-am febră», the hyphen a space) is «Am ___», es «No tengo fiebre» is «Tengo ___», it «Non
// ho la febbre» is «Ho ___» with the article in the value. CATCHES the negation of a language whose pack writes no `after`
// counted as a word added (one difference: the construction only almost said), and a negation free in a pack that does
// not list it.
it('reads a construction said in the negative as the construction in every language, the negation anywhere', function () {
    $pl = fjPack('pl', ['negation' => ['words' => ['nie'], 'after' => null, 'do_support' => []]]);
    $ro = fjPack('ro', ['negation' => ['words' => ['nu', 'n'], 'after' => null, 'do_support' => []]]);
    $es = fjPack('es', ['negation' => ['words' => ['no'], 'do_support' => []]]);
    $it = fjPack('it', ['negation' => ['words' => ['non'], 'after' => null, 'do_support' => []], 'articles' => ['il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una']]);

    expect(fjOne('Nie mam gorączki.', 'Mam ___.', $pl))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'gorączki']))
        ->and(fjOne('Nu am febră', 'Am ___.', $ro))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'febră']))
        ->and(fjOne('N-am febră', 'Am ___.', $ro))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'febră']))
        // `after` not written at all is «anywhere» too.
        ->and(fjOne('No tengo fiebre', 'Tengo ___.', $es))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'fiebre']))
        ->and(fjOne('Non ho la febbre', 'Ho ___.', $it))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'la febbre']))
        // Without the key the same move is one word added — almost.
        ->and(fjOne('Nie mam gorączki.', 'Mam ___.', fjPack('pl')))->toEqual(new MoveVerdict([], ['x:p1']))
        // The frame's own negation left out of the move is a difference like any other: a positive move is at most almost
        // a negative frame.
        ->and(fjOne('Mam gorączkę.', 'Nie mam ___.', $pl))->toEqual(new MoveVerdict([], ['x:p1']))
        // A negation beyond it in the frame's words still leaves one difference to count.
        ->and(fjOne('Nie mam dużej gorączki', 'Mam wysoką ___.', $pl))->toEqual(new MoveVerdict([], ['x:p1']));
});

// The key spec (docs/research/lang-1/pack-keys.md §4.3): «`'after' => []` (пустой список) — ни после чего, т. е. никогда;
// не путайте с null», and «каждая запись читается как слово хода: «N'», «Nicht» тоже сработают». CATCHES an empty `after`
// read as «anywhere» (a pack that switches the rule off with [] forgiving the negation everywhere), and the negation's
// entries compared as written — an elided «N’» or a capitalised «Pas» of a pack never meeting the move's «ne», «pas».
it('reads an empty after as never, and the negation\'s entries as the move\'s words are read', function () {
    $never = fjPack('pl', ['negation' => ['words' => ['nie'], 'after' => [], 'do_support' => []]]);
    $fr = fjPack('fr', [
        'contractions' => ["j'" => 'je', "n'" => 'ne'],
        'negation' => ['words' => ['N’', 'Pas'], 'after' => null, 'do_support' => []],
    ]);

    expect(fjOne('Nie mam gorączki.', 'Mam ___.', $never))->toEqual(new MoveVerdict([], ['x:p1']))
        ->and(fjOne('Mam gorączkę.', 'Mam ___.', $never)->said)->toBe(['x:p1'])
        ->and(fjOne("Je n'ai pas de fièvre.", "J'ai ___.", $fr))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'pas de fièvre']));
});

// Canon (наряд LANG-1 §1): «Je n'ai pas de fièvre» vs «J'ai ___» is said — the elisions «j'», «n'» spelt out by the pack's
// contractions (keys ending with an apostrophe), «ne» and «pas» both free anywhere, either apostrophe. The window keeps
// «pas de fièvre»: the negation in the window is the learner's value. CATCHES an elision read as one word («jai», «nai»),
// the typographic apostrophe of a phone keyboard not read as one, and the spoken negation without «ne» not taken.
it('reads a French negation around an elided verb as the construction', function () {
    $fr = fjPack('fr', [
        'contractions' => ["j'" => 'je', "n'" => 'ne', "l'" => 'le', "d'" => 'de', "qu'" => 'que'],
        'negation' => ['words' => ['ne', 'pas'], 'after' => null, 'do_support' => []],
        'articles' => ['le', 'la', 'les', 'un', 'une'],
    ]);

    expect(fjOne("Je n'ai pas de fièvre.", "J'ai ___.", $fr))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'pas de fièvre']))
        ->and(fjOne('Je n’ai pas de fièvre', 'J’ai ___.', $fr))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'pas de fièvre']))
        ->and(fjOne("J'ai pas de fièvre", "J'ai ___.", $fr)->said)->toBe(['x:p1'])
        ->and(fjOne("J'ai de la fièvre depuis hier", "J'ai ___ depuis hier.", $fr))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'de la fièvre']))
        // The elided article is the article: «l'hôpital» is «hôpital» to the comparison, «l'» in the value as said.
        ->and(fjOne("Je vais à l'hôpital", 'Je vais à ___.', $fr))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => "l'hôpital"]))
        // Without the elisions «j'ai» is one word no move of the learner's has — «Je» in its place is a difference.
        ->and(fjOne("Je n'ai pas de fièvre.", "J'ai ___.", fjPack('fr', ['negation' => ['words' => ['ne', 'pas'], 'after' => null]])))->toEqual(new MoveVerdict([], ['x:p1']));
});

// Canon (наряд LANG-1 §1): de «nicht»/«kein…» are free anywhere. «Ich habe keine Zeit am Montag» says «Ich habe Zeit am
// ___»; «Das passt mir nicht» says «Das passt mir.» and is only almost «Das passt mir am ___.» — «am» is left out and the
// window has nothing but the negation; «Das passt mir am Montag nicht» says it, the negation in the window the learner's.
// CATCHES «keine» counted as a word added, and a negation that makes a construction said with nothing in its window.
it('reads a German negation as sensibly: free inside the frame\'s words, the learner\'s own in the window', function () {
    $de = fjPack('de', ['negation' => ['words' => ['nicht', 'kein', 'keine', 'keinen', 'keinem', 'keiner', 'keines'], 'after' => null, 'do_support' => []]]);

    expect(fjOne('Ich habe keine Zeit am Montag', 'Ich habe Zeit am ___.', $de))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'Montag']))
        ->and(fjOne('Das passt mir nicht.', 'Das passt mir.', $de)->said)->toBe(['x:p1'])
        ->and(fjOne('Das passt mir nicht', 'Das passt mir am ___.', $de))->toEqual(new MoveVerdict([], ['x:p1']))
        ->and(fjOne('Das passt mir am Montag nicht', 'Das passt mir am ___.', $de))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'Montag nicht']))
        ->and(fjOne('Das passt mir nicht am Montag', 'Das passt mir am ___.', $de))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'Montag']));
});

// Canon (наряд LANG-1 §1): «старая en-форма с одной строкой word продолжает работать (читается как words: [word])», and
// English keeps its `after` list — «not» is free after be, a modal or have, nowhere else. CATCHES the new shape read
// differently from the old one, and English «not» made free anywhere by the generalisation.
it('keeps English negation as it was, in the old shape and the new', function () {
    $en = require dirname(__DIR__, 3).'/config/lesson/lang/en.php';
    $after = $en['negation']['after'];
    $old = new LanguagePack('en', ['negation' => ['word' => 'not', 'do_support' => ['do', 'does', 'did'], 'after' => $after]] + $en);
    $new = new LanguagePack('en', ['negation' => ['words' => ['not'], 'do_support' => ['do', 'does', 'did'], 'after' => $after]] + $en);
    $moves = ['I don\'t have any experience', 'I am not working on anything', 'Not I have some shoulder pain', 'I have not some shoulder pain', 'no, he doesn\'t have a fever'];
    $fever = [new ConversationPhrase('d', 'p1', 'He has ___.', 'У него ___.', null, null)];

    foreach ($moves as $move) {
        expect((new FrameJudge)->move($move, [...fjTrainer(), ...$fever], $new))->toEqual((new FrameJudge)->move($move, [...fjTrainer(), ...$fever], $old));
    }
    expect(fjJudge('Not I have some shoulder pain', fjTrainer()))->toEqual(new MoveVerdict([], ['s2:p3']))
        ->and(fjJudge('I have not some shoulder pain', fjTrainer())->said)->toBe(['s2:p3']);
});

// Canon (наряд LANG-1 §1): the words are compared in the kernel's folded form — ß is ss, ş (cedilla) is ș (comma), œ is
// oe — on both sides; and a Spanish question keeps its ¿ out of the words. CATCHES «Ich heisse Anna» not said for «Ich
// heiße ___» and a recogniser's cedilla «Aş» not meeting the frame's «Aș».
it('compares the words in one spelling of a letter, and reads past the Spanish opening marks', function () {
    $es = fjPack('es', ['intro_words' => ['sí', 'vale'], 'negation' => ['words' => ['no'], 'after' => null]]);

    expect(fjOne('Ich heisse Anna', 'Ich heiße ___.', fjPack('de'))->said)->toBe(['x:p1'])
        ->and(fjOne('Ich heiße Anna', 'Ich heisse ___.', fjPack('de'))->said)->toBe(['x:p1'])
        ->and(fjOne('Aş vrea o cafea', 'Aș vrea ___.', fjPack('ro'))->said)->toBe(['x:p1'])
        ->and(fjOne('Sí, ¿puedo pagar con tarjeta?', '¿Puedo pagar con ___?', $es))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'tarjeta']))
        ->and(fjOne('¡Vale! ¿Puedo pagar con tarjeta?', '¿Puedo pagar con ___?', $es)->said)->toBe(['x:p1'])
        ->and(fjOne('¿No puedo pagar con tarjeta?', '¿Puedo pagar con ___?', $es)->said)->toBe(['x:p1']);
});
