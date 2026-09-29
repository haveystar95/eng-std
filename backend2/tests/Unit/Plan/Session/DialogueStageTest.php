<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\CardObjects;
use App\Modules\Plan\Domain\Assembly\DialogueStage;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Lesson\CheckOption;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\ExchangeCheck;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «ДИАЛОГ» (наряд SESSION-1a, разд. 1–2; SPEC §4, D-17, D-18): the visit exchange by exchange — an answer is understood
 * then replied to, an ask is said first, a rescue is one card and stands inside the answer it rescues; the options of a
 * check are the exchange's own and nothing else (наряд FIX-3 §5); a voice card carries every mode.
 *
 * The fake lesson (8 exchanges): x1–x5 answer (x4 on a frame without a slot), x6 rescue, x7 and x8 ask on one frame.
 */

const S1DLG_SCENE = '01J8SESS10N1D1A10GXE000000';

/** @param (callable(list<Exchange>): list<Exchange>)|null $edit what to do to the served exchanges before dealing */
function s1dlgScene(?callable $edit = null): SceneMaterial
{
    $sceneId = PlanSceneId::fromString(S1DLG_SCENE);
    $packs = lessonPacks();
    $payload = FakePlanModel::lessonPayload(FakePlanModel::lessonRequest('Приём у врача'));
    $lesson = planServed((new LessonParser)->parse($payload), $sceneId->value, $packs->for('en'));
    $terms = planTermsOf($sceneId, $lesson);
    if ($edit !== null) {
        $lesson = $lesson->withExchanges($edit($lesson->exchanges));
    }

    return new SceneMaterial($sceneId, $lesson, $terms, $packs->for('en'), $packs->for('ru'));
}

/**
 * @param  list<CardDraft>  $drafts
 * @return list<string>
 */
function s1dlgOrder(array $drafts): array
{
    return array_map(static fn (CardDraft $d): string => $d->kind->value.':'.$d->unitRef, $drafts);
}

/** @param list<CardDraft> $drafts */
function s1dlgCard(array $drafts, string $kind, string $ref): CardDraft
{
    foreach ($drafts as $draft) {
        if ($draft->kind->value === $kind && $draft->unitRef === $ref) {
            return $draft;
        }
    }
    throw new RuntimeException("No {$kind} on {$ref}.");
}

/** @param list<Exchange> $exchanges */
function s1dlgStep(array $exchanges, int $step): Exchange
{
    foreach ($exchanges as $exchange) {
        if ($exchange->step === $step) {
            return $exchange;
        }
    }
    throw new RuntimeException("No exchange {$step}.");
}

/** The same learner line standing on another frame — or on none. */
function s1dlgOnFrame(Exchange $exchange, ?string $phraseId): Exchange
{
    return $exchange->withMessages(array_map(
        static fn (Message $m): Message => ! $m->isLearner() ? $m : new Message(
            $m->speaker, $m->roleTarget, $m->roleNative, $m->textTarget, $m->textNative,
            $m->pronunciationNative, $m->speakingKey, $m->simplifiedVariants, $phraseId, $m->filler,
        ),
        $exchange->messages,
    ));
}

/**
 * @param  array<string, mixed>  $payload
 * @return list<string>
 */
function s1dlgOptionTexts(array $payload): array
{
    return array_column($payload['options'], 'text');
}

/** @param array<string, mixed> $payload */
function s1dlgCorrectText(array $payload): string
{
    return array_column($payload['options'], 'text', 'id')[$payload['correct']];
}

// D-17 (canvas 33-6), разд. 2: answer → partner, answer; the rescue right after x5 stands between x5's two cards;
// ask → ONE card, `dialogue_ask`, which carries the exchange's check itself (наряд BACK-TAILS-1 §1.5, кадр 33-5).
it('deals the visit in its order — x1..x4 partner and answer, x5 partner, x6 rescue, x5 answer, then x7 and x8 as one ask each', function () {
    $scene = s1dlgScene();
    $drafts = (new DialogueStage)->build($scene);

    expect(s1dlgOrder($drafts))->toBe([
        'dialogue_partner:x1', 'dialogue_answer:x1',
        'dialogue_partner:x2', 'dialogue_answer:x2',
        'dialogue_partner:x3', 'dialogue_answer:x3',
        'dialogue_partner:x4', 'dialogue_answer:x4',
        'dialogue_partner:x5', 'dialogue_rescue:x6', 'dialogue_answer:x5',
        'dialogue_ask:x7',
        'dialogue_ask:x8',
    ]);
    foreach ($drafts as $draft) {
        expect($draft->unitKind)->toBe(UnitKind::Exchange)
            ->and($draft->source)->toBe(CardSource::Today)
            ->and($draft->sourceDayId)->toBeNull()
            ->and(array_key_first($draft->payload))->toBe('scene_id')
            ->and($draft->payload['scene_id'])->toBe(S1DLG_SCENE)
            ->and($draft->payload['exchange'])->toBe(['ref' => $draft->unitRef, 'step' => (int) substr($draft->unitRef, 1), 'kind' => $draft->payload['exchange']['kind']]);
    }
    expect(s1dlgCard($drafts, 'dialogue_answer', 'x5')->payload['exchange']['kind'])->toBe('answer')
        ->and(s1dlgCard($drafts, 'dialogue_rescue', 'x6')->payload['exchange']['kind'])->toBe('rescue')
        ->and(s1dlgCard($drafts, 'dialogue_ask', 'x7')->payload['exchange']['kind'])->toBe('ask')
        // A day dealt again is the same day.
        ->and((new DialogueStage)->build(s1dlgScene()))->toEqual($drafts);
});

// Canon 33-5 (наряд BACK-TAILS-1 §1.5): the learner speaks first, the partner's answer sounds with its text closed, and
// the check of that exchange is asked ON THE SAME CARD — same keys, same names as `dialogue_partner`, same options and
// same seed. Catches the check card coming back for an ask (one screen dealt as two) and the ask keeping the voice keys
// while losing the check.
it('deals an ask as one card: the learner\'s own line, the partner\'s answer, and the exchange\'s own check on it', function () {
    $drafts = (new DialogueStage)->build(s1dlgScene());
    $ask = s1dlgCard($drafts, 'dialogue_ask', 'x8')->payload;

    expect($ask['own_line'])->toBe([
        'ref' => 'x8b',
        'text_target' => 'Do we need a follow-up appointment?',
        'text_native' => 'Нам нужно прийти на повторный приём?',
        'frame_ref' => 'p6',
        'filler_index' => 1,
        'key' => s1dlgStep(s1dlgScene()->lesson->exchanges, 8)->learner()->speakingKey,
        'audio' => Audio::of('x8b'),
    ])
        ->and($ask['own_line']['key'])->not->toBeNull()
        ->and($ask['partner_line'])->toBe([
            'ref' => 'x8',
            'text_target' => 'Only if it still hurts after one week.',
            'text_native' => 'Только если через неделю ещё будет болеть.',
            'audio' => Audio::of('x8'),
        ])
        ->and($ask['frame']['ref'])->toBe('p6')
        ->and(s1dlgCard($drafts, 'dialogue_ask', 'x7')->payload['own_line']['filler_index'])->toBe(0)
        // The check rides on the ask itself, under the names `dialogue_partner` uses.
        ->and($ask['question_native'])->toBe('Когда нужно прийти снова?')
        ->and(s1dlgCorrectText($ask))->toBe('Если боль не пройдёт через семь дней')
        ->and(array_column($ask['options'], 'id'))->toBe(['o1', 'o2', 'o3'])
        ->and(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind->value === 'dialogue_partner' && $d->unitRef === 'x8'))->toBe([])
        // The voice keys stay: it is still the card the learner says their line on.
        ->and(array_keys($ask))->toBe(['scene_id', 'exchange', 'partner_line', 'own_line', 'frame', 'modes', 'speech_mode', 'question_native', 'options', 'correct']);
});

// Canon (наряд BACK-TAILS-1 §1.5, терпимость §6): «три ключа проверки приходят вместе или не приходят вовсе». Catches
// an ask card dealt with a half-built check — a question with nothing to choose between — and an ask card lost
// altogether because its check could not be built: the learner's own line is what that card is for.
it('deals the ask without the three check keys when its check cannot be built, and never half of them', function () {
    // x8's three options read alike — case and spaces aside, one option. No other exchange lends it one (наряд FIX-3 §5):
    // nothing to choose between, nothing to ask — whatever else the visit holds.
    $alike = static fn (Exchange $e): Exchange => $e->withCheck(new ExchangeCheck(
        $e->check->textTarget, $e->check->textNative,
        [new CheckOption('a', 'Через неделю'), new CheckOption('b', 'через неделю'), new CheckOption('c', ' ЧЕРЕЗ НЕДЕЛЮ ')],
        0, $e->check->explanationNative,
    ));
    $two = (new DialogueStage)->build(s1dlgScene(static fn (array $x): array => [
        s1dlgStep($x, 7)->withStep(1), $alike(s1dlgStep($x, 8))->withStep(2),
    ]));
    expect(array_keys(s1dlgCard($two, 'dialogue_ask', 'x2')->payload))->not->toContain('question_native')
        ->and(array_keys(s1dlgCard($two, 'dialogue_ask', 'x1')->payload))->toContain('question_native');

    $alone = (new DialogueStage)->build(s1dlgScene(static fn (array $x): array => [$alike(s1dlgStep($x, 8))->withStep(1)]));
    $ask = s1dlgCard($alone, 'dialogue_ask', 'x1')->payload;

    expect(array_keys($ask))->toBe(['scene_id', 'exchange', 'partner_line', 'own_line', 'frame', 'modes', 'speech_mode'])
        ->and(array_intersect(['question_native', 'options', 'correct'], array_keys($ask)))->toBe([]);
});

// Canon: a rescue is ONE card; the line not caught is the partner's line of the exchange before; the partner repeats.
it('deals a rescue once — inside the answer before it, at its own place after an ask, with nothing asked when it opens the visit', function () {
    $drafts = (new DialogueStage)->build(s1dlgScene());
    $rescues = array_values(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind->value === 'dialogue_rescue'));

    expect($rescues)->toHaveCount(1)
        ->and($rescues[0]->unitRef)->toBe('x6')
        ->and($rescues[0]->payload)->toBe([
            'scene_id' => S1DLG_SCENE,
            'exchange' => ['ref' => 'x6', 'step' => 6, 'kind' => 'rescue'],
            'asked_line' => [
                'ref' => 'x5',
                'text_target' => 'It looks like a muscle strain, so he should rest and use a heating pad.',
                'text_native' => 'Похоже на растяжение мышцы, так что ему нужен покой и грелка.',
                'audio' => Audio::of('x5'),
            ],
            'rescue_line' => [
                'ref' => 'x6b',
                'text_target' => 'Sorry, could you say that more slowly?',
                'text_native' => 'Простите, можно помедленнее?',
                'audio' => Audio::of('x6b'),
            ],
            'partner_repeat' => [
                'ref' => 'x6',
                'text_target' => 'He should rest and use a heating pad.',
                'text_native' => 'Ему нужен покой и грелка.',
                'audio' => Audio::of('x6'),
            ],
            'slow_rate' => 0.75,
            'expected_text' => 'Sorry, could you say that more slowly?',
            'speech_mode' => 'repeat',
        ]);

    // After an ask the rescue is not pulled inside anything: ask, partner, rescue — in the order of the visit.
    $afterAsk = (new DialogueStage)->build(s1dlgScene(static fn (array $x): array => [
        s1dlgStep($x, 1), s1dlgStep($x, 7)->withStep(2), s1dlgStep($x, 6)->withStep(3),
    ]));
    expect(s1dlgOrder($afterAsk))->toBe(['dialogue_partner:x1', 'dialogue_answer:x1', 'dialogue_ask:x2', 'dialogue_rescue:x3'])
        ->and(s1dlgCard($afterAsk, 'dialogue_rescue', 'x3')->payload['asked_line']['text_target'])->toBe('No, you do not need that for a muscle strain.')
        ->and(s1dlgCard($afterAsk, 'dialogue_rescue', 'x3')->payload['asked_line']['ref'])->toBe('x2');

    // A rescue that opens the visit has asked nothing yet.
    $first = (new DialogueStage)->build(s1dlgScene(static fn (array $x): array => [
        s1dlgStep($x, 6)->withStep(1), s1dlgStep($x, 1)->withStep(2),
    ]));
    expect(s1dlgOrder($first))->toBe(['dialogue_rescue:x1', 'dialogue_partner:x2', 'dialogue_answer:x2'])
        ->and(s1dlgCard($first, 'dialogue_rescue', 'x1')->payload['asked_line'])->toBeNull();
});

// Canon: «обмен с одним сообщением — в диалоге только лентой, карточек нет».
it('deals no card for an exchange with one message, and does not pull a one-message rescue into the answer before it', function () {
    $drafts = (new DialogueStage)->build(s1dlgScene(static fn (array $x): array => array_map(
        static fn (Exchange $e): Exchange => in_array($e->step, [3, 6], true) ? $e->withMessages([$e->messages[0]]) : $e,
        $x,
    )));

    expect(s1dlgOrder($drafts))->toBe([
        'dialogue_partner:x1', 'dialogue_answer:x1',
        'dialogue_partner:x2', 'dialogue_answer:x2',
        'dialogue_partner:x4', 'dialogue_answer:x4',
        'dialogue_partner:x5', 'dialogue_answer:x5',
        'dialogue_ask:x7',
        'dialogue_ask:x8',
    ]);
});

// An ANSWER keeps its check card when the learner line stands on no frame; an ASK loses everything — the check lived on
// the card the frame carries (наряд BACK-TAILS-1 §1.5).
it('deals only the partner card for an answer on no frame, and nothing at all for such an ask', function () {
    $drafts = (new DialogueStage)->build(s1dlgScene(static fn (array $x): array => array_map(
        static fn (Exchange $e): Exchange => match ($e->step) {
            2 => s1dlgOnFrame($e, 'p99'),
            7 => s1dlgOnFrame($e, null),
            default => $e,
        },
        $x,
    )));

    expect(s1dlgOrder($drafts))->toBe([
        'dialogue_partner:x1', 'dialogue_answer:x1',
        'dialogue_partner:x2',
        'dialogue_partner:x3', 'dialogue_answer:x3',
        'dialogue_partner:x4', 'dialogue_answer:x4',
        'dialogue_partner:x5', 'dialogue_rescue:x6', 'dialogue_answer:x5',
        'dialogue_ask:x8',
    ]);
});

// Canon (наряд FIX-3 §5): «заём четвёртого варианта из самого дальнего обмена удалить: варианты — только из проверки
// своего обмена. Урок отдал три — три; отдал четыре — четыре». The owner's gym day put «К ушам» — the right answer of the
// last exchange — under «О чём спрашивает тренер?». CATCHES the borrowed fourth coming back, and options of another
// exchange's check.
it('offers the options of the exchange\'s own check and nothing else — three when the lesson wrote three, four when four', function () {
    $drafts = (new DialogueStage)->build(s1dlgScene());
    $x1 = s1dlgCard($drafts, 'dialogue_partner', 'x1')->payload;
    $x5 = s1dlgCard($drafts, 'dialogue_partner', 'x5')->payload;

    expect($x1['question_native'])->toBe('О каких двух местах спрашивает врач?')
        ->and($x1['partner_line'])->toBe(CardObjects::partnerLine(s1dlgStep(s1dlgScene()->lesson->exchanges, 1)))
        ->and(array_column($x1['options'], 'id'))->toBe(['o1', 'o2', 'o3'])
        ->and(array_map(static fn (array $o): array => array_keys($o), $x1['options']))->each->toBe(['id', 'text'])
        ->and(s1dlgOptionTexts($x1))->toEqualCanonicalizing(['Верх или низ спины', 'Шея или голова', 'Колени или ступни'])
        ->and(s1dlgCorrectText($x1))->toBe('Верх или низ спины')
        ->and(array_filter($x1['options'], static fn (array $o): bool => $o['id'] === $x1['correct']))->toHaveCount(1)
        ->and(s1dlgOptionTexts($x5))->toEqualCanonicalizing(['Перелом кости', 'Растянутая мышца', 'Сильная простуда'])
        ->and(s1dlgCorrectText($x5))->toBe('Растянутая мышца');

    // A check the lesson wrote with four options is asked with four.
    $four = s1dlgScene(static fn (array $x): array => array_map(
        static fn (Exchange $e): Exchange => $e->step !== 1 ? $e : $e->withCheck(new ExchangeCheck(
            $e->check->textTarget, $e->check->textNative,
            [...$e->check->options, new CheckOption('The arm or the hand', 'Рука или кисть')],
            $e->check->correctOptionIndex, $e->check->explanationNative,
        )),
        $x,
    ));
    expect(s1dlgOptionTexts(s1dlgCard((new DialogueStage)->build($four), 'dialogue_partner', 'x1')->payload))
        ->toEqualCanonicalizing(['Верх или низ спины', 'Шея или голова', 'Колени или ступни', 'Рука или кисть']);
});

// Canon 33-2/33-3/33-4: the card does not pick the mode — chips for beginner, the line as a hint, the frame blind.
it('gives the voice cards every mode — the fillers as chips, the line as the hint, the frame blind — and the frame\'s coverage', function () {
    $drafts = (new DialogueStage)->build(s1dlgScene());
    $x1 = s1dlgCard($drafts, 'dialogue_answer', 'x1')->payload;
    $x2 = s1dlgCard($drafts, 'dialogue_answer', 'x2')->payload;
    $x4 = s1dlgCard($drafts, 'dialogue_answer', 'x4')->payload;
    $x7 = s1dlgCard($drafts, 'dialogue_ask', 'x7')->payload;

    expect($x1['modes']['chips'])->toBe($x1['frame']['slot']['fillers'])
        ->and(array_column($x1['modes']['chips'], 'target'))->toBe(['lower back', 'neck', 'shoulder'])
        ->and($x1['modes']['voice_hint'])->toBe('It hurts in his lower back.')
        ->and($x1['modes']['voice_hint'])->toBe($x1['own_line']['text_target'])
        ->and($x1['modes']['voice_blind'])->toBe('It hurts in his ___.')
        ->and($x1['modes']['voice_blind'])->toBe($x1['frame']['frame_target'])
        // The learner says their own line: the key is the frame's own words, the window is anyone's.
        ->and($x1['speech_mode'])->toBe('free')
        ->and($x2['speech_mode'])->toBe('free')
        ->and($x2['modes']['voice_blind'])->toBe('It started ___.')
        // A frame without a slot has no chips.
        ->and($x4['frame']['slot'])->toBeNull()
        ->and($x4['modes'])->toBe(['chips' => [], 'voice_hint' => "No, he doesn't have a fever.", 'voice_blind' => "He doesn't have a fever."])
        ->and($x4['own_line']['filler_index'])->toBeNull()
        ->and($x4['own_line']['frame_ref'])->toBe('p4')
        ->and($x7['modes']['chips'])->toBe($x7['frame']['slot']['fillers'])
        ->and($x7['modes']['voice_blind'])->toBe('Do we need ___?')
        ->and($x7['speech_mode'])->toBe('free')
        ->and($x1['own_line'])->toMatchArray(['ref' => 'x1b', 'frame_ref' => 'p1', 'filler_index' => 0, 'audio' => Audio::of('x1b')]);

    // The frame's fillers: the served marks, the learner's-language line, the phrase's own file for the filler it is said with.
    expect($x1['frame'])->toBe([
        'ref' => 'p1',
        'kind' => 'answer',
        'frame_target' => 'It hurts in his ___.',
        'frame_native' => 'У него болит ___.',
        'frame_pronunciation_native' => 'ит хёртс ин хиз ___',
        'slot' => [
            'hint_native' => 'где болит',
            'fillers' => [
                ['index' => 0, 'target' => 'lower back', 'native' => 'поясница', 'pronunciation_native' => 'лоуэр бэк', 'in_dialogue' => true, 'native_line' => 'У него болит поясница.', 'audio' => Audio::of('p1')],
                ['index' => 1, 'target' => 'neck', 'native' => 'шея', 'pronunciation_native' => 'нэк', 'in_dialogue' => false, 'native_line' => 'У него болит шея.', 'audio' => Audio::of('p1.f2')],
                ['index' => 2, 'target' => 'shoulder', 'native' => 'плечо', 'pronunciation_native' => 'шоулдер', 'in_dialogue' => false, 'native_line' => 'У него болит плечо.', 'audio' => Audio::of('p1.f3')],
            ],
        ],
    ])
        ->and(array_column($x7['frame']['slot']['fillers'], 'in_dialogue'))->toBe([true, true, false])
        ->and($x7['frame']['slot']['fillers'][2]['native_line'])->toBe('Нам нужно взять справку для школы?')
        ->and($x7['frame']['kind'])->toBe('ask');
});

it('keeps the exact keys of every dialogue card and of the pieces they show', function () {
    $drafts = (new DialogueStage)->build(s1dlgScene());
    $line = ['ref', 'text_target', 'text_native', 'audio'];
    $audio = ['ref', 'voice', 'url', 'duration_ms'];

    $partner = s1dlgCard($drafts, 'dialogue_partner', 'x2')->payload;
    expect(array_keys($partner))->toBe(['scene_id', 'exchange', 'partner_line', 'question_native', 'options', 'correct'])
        ->and(array_keys($partner['exchange']))->toBe(['ref', 'step', 'kind'])
        ->and(array_keys($partner['partner_line']))->toBe($line)
        ->and(array_keys($partner['partner_line']['audio']))->toBe($audio)
        ->and($partner['partner_line']['audio'])->toBe(['ref' => 'x2', 'voice' => 'partner', 'url' => null, 'duration_ms' => null]);

    // The ask carries the three keys of the check after its own (наряд BACK-TAILS-1 §1.5); the answer does not.
    foreach ([s1dlgCard($drafts, 'dialogue_answer', 'x2'), s1dlgCard($drafts, 'dialogue_ask', 'x7')] as $card) {
        $p = $card->payload;
        $voice = ['scene_id', 'exchange', 'partner_line', 'own_line', 'frame', 'modes', 'speech_mode'];
        expect(array_keys($p))->toBe($card->kind->value === 'dialogue_ask' ? [...$voice, 'question_native', 'options', 'correct'] : $voice)
            ->and(array_keys($p['partner_line']))->toBe($line)
            ->and(array_keys($p['own_line']))->toBe(['ref', 'text_target', 'text_native', 'frame_ref', 'filler_index', 'key', 'audio'])
            ->and($p['own_line']['audio']['voice'])->toBe('learner')
            ->and(array_keys($p['frame']))->toBe(['ref', 'kind', 'frame_target', 'frame_native', 'frame_pronunciation_native', 'slot'])
            ->and(array_keys($p['frame']['slot']))->toBe(['hint_native', 'fillers'])
            ->and(array_keys($p['frame']['slot']['fillers'][0]))->toBe(['index', 'target', 'native', 'pronunciation_native', 'in_dialogue', 'native_line', 'audio'])
            ->and(array_keys($p['frame']['slot']['fillers'][0]['audio']))->toBe($audio)
            ->and(array_keys($p['modes']))->toBe(['chips', 'voice_hint', 'voice_blind']);
    }

    $rescue = s1dlgCard($drafts, 'dialogue_rescue', 'x6')->payload;
    expect(array_keys($rescue))->toBe(['scene_id', 'exchange', 'asked_line', 'rescue_line', 'partner_repeat', 'slow_rate', 'expected_text', 'speech_mode'])
        ->and(array_keys($rescue['asked_line']))->toBe($line)
        ->and(array_keys($rescue['rescue_line']))->toBe($line)
        ->and(array_keys($rescue['partner_repeat']))->toBe($line);
});
