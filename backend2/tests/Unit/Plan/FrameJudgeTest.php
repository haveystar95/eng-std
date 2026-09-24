<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\MoveVerdict;

/**
 * THE JUDGE OF THE TALK'S CONSTRUCTIONS — A COHERENT PHRASE, NOT A BAG OF WORDS (наряд FIX-4 §2), on the frames of the
 * owner's gym rehearsal (plan 2DX8QC): «Ресепшен зала» and «С тренером», seven frames each.
 */

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
        ->and((new FrameJudge)->move('я здесь и это мой первый визит', $frame, $ru))->toEqual(new MoveVerdict([], ['r:p1']));
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
