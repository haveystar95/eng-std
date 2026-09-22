<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\Exception\ConversationEnded;
use App\Modules\Plan\Domain\Exception\ConversationNotYourTurn;
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\ConversationState;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\ConversationType;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\TurnCost;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * THE TALK AS AN AGGREGATE (наряд CONV-1): the journal is append-only, a move is only the learner's when it IS their
 * move, the scene walks forward, and «сколько ходов осталось» is not «сколько денег осталось».
 */
function convTalk(ConversationType $type = ConversationType::Day, int $turns = 4, bool $hints = true, array $scenes = ['s1', 's2']): Conversation
{
    return Conversation::start(
        id: ConversationId::fromString('01J8CAAA000000000000000001'),
        planId: PlanId::fromString('01J8CAAA000000000000000002'),
        userId: UserId::fromString('01J8CAAA000000000000000003'),
        dayId: PlanDayId::fromString('01J8CAAA000000000000000004'),
        dayNumber: 2,
        type: $type,
        sceneIds: $scenes,
        turnLimit: $turns,
        hintsEnabled: $hints,
        now: new DateTimeImmutable('2026-09-21T10:00:00Z'),
    );
}

function convAgent(Conversation $talk, ?string $checkpoint = null, ?string $hint = 'скажи, что болит', string $cost = '0.010000', ?bool $offTopic = false): void
{
    $talk->recordAgentTurn(ConversationTurn::agent(
        id: ConversationTurnId::generate(),
        conversationId: $talk->id(),
        index: $talk->nextIndex(),
        textTarget: 'What brings you in today?',
        textNative: 'Что вас беспокоит?',
        audio: null,
        checkpointDone: $checkpoint,
        hintNative: $hint,
        cost: new TurnCost(modelCostUsd: $cost),
        now: new DateTimeImmutable('2026-09-21T10:00:05Z'),
        understood: true,
        offTopic: $offTopic,
    ));
    $talk->spend($cost);
}

function convLearner(Conversation $talk, TurnKind $kind = TurnKind::Said, array $phrases = [], string $at = '2026-09-21T10:00:10Z'): void
{
    $talk->recordLearnerTurn(ConversationTurn::learner(
        id: ConversationTurnId::generate(),
        conversationId: $talk->id(),
        index: $talk->nextIndex(),
        kind: $kind,
        heard: $kind === TurnKind::Said ? 'It started three days ago.' : null,
        phrasesUsed: $phrases,
        now: new DateTimeImmutable($at),
    ));
}

/** The role's line said at a given moment — the gaps between lines are what the talk's minutes are made of. */
function convAgentAt(Conversation $talk, string $at, string $text = 'What brings you in today?'): void
{
    $talk->recordAgentTurn(ConversationTurn::agent(
        id: ConversationTurnId::generate(),
        conversationId: $talk->id(),
        index: $talk->nextIndex(),
        textTarget: $text,
        textNative: 'Что вас беспокоит?',
        audio: null,
        checkpointDone: null,
        hintNative: null,
        cost: new TurnCost,
        now: new DateTimeImmutable($at),
    ));
}

// Catches a client that sends a second move while the first is still being answered — two replies to one line — and a
// ribbon written into sideways: the index is the aggregate's, never the caller's.
it('takes a move only when it is the learner\'s, and numbers the journal itself', function () {
    $talk = convTalk();

    expect($talk->state())->toBe(ConversationState::AgentTurn)
        ->and(fn () => convLearner($talk))->toThrow(ConversationNotYourTurn::class);

    convAgent($talk);
    expect($talk->state())->toBe(ConversationState::YourTurn)
        ->and($talk->nextIndex())->toBe(2);

    convLearner($talk);
    expect($talk->state())->toBe(ConversationState::AgentTurn)
        ->and(array_map(static fn (ConversationTurn $t): int => $t->index, $talk->turns()))->toBe([1, 2])
        ->and(fn () => convLearner($talk))->toThrow(ConversationNotYourTurn::class);
});

// Canon: «Ещё раз» is a NEW talk, never this one reopened — what was said stays said.
it('takes nothing more once it has ended', function () {
    $talk = convTalk();
    convAgent($talk);
    $talk->end(ConversationEnd::Natural, new DateTimeImmutable('2026-09-21T10:03:00Z'));

    expect($talk->isEnded())->toBeTrue()
        ->and(fn () => convLearner($talk))->toThrow(ConversationEnded::class)
        ->and(fn () => convAgent($talk))->toThrow(ConversationEnded::class);
});

/**
 * Canon (наряд CONV-2, п. 3): «summary.minutes — время разговора, не часов: сумма промежутков между соседними ходами,
 * каждый ≤ 60 с; разговор, пролежавший открытым пять часов, — 2 минуты». Catches the wall clock between the start and
 * the end — «Разговор окончен · 323 минуты» on the phone (CLIENT-CONV-1a, §5 п. 12) — and a gap counted in full when
 * the learner walked away from the talk.
 */
it('counts the minutes the talk was talked, not the hours it stood open', function () {
    $talk = convTalk();
    convAgentAt($talk, '2026-09-21T10:00:00Z');
    convLearner($talk, at: '2026-09-21T10:00:40Z');          // 40 s
    convAgentAt($talk, '2026-09-21T10:00:43Z');             //  3 s
    convLearner($talk, at: '2026-09-21T15:00:43Z');          // five hours away — counts 60 s
    convAgentAt($talk, '2026-09-21T15:00:46Z');             //  3 s
    convLearner($talk, at: '2026-09-21T15:01:00Z');          // 14 s
    $talk->end(ConversationEnd::Natural, new DateTimeImmutable('2026-09-21T15:01:00Z'));

    expect($talk->activeSeconds())->toBe(40 + 3 + 60 + 3 + 14)
        ->and($talk->minutes())->toBe(2);

    // A talk with one line has been talked for no time at all — and still says «1 минута» when it is over.
    $short = convTalk();
    convAgentAt($short, '2026-09-21T10:00:00Z');
    $short->end(ConversationEnd::Replayed, new DateTimeImmutable('2026-09-21T11:00:00Z'));
    expect($short->activeSeconds())->toBe(0)->and($short->minutes())->toBe(1);
});

/**
 * Canon (наряд CONV-2, п. 2): «„Ещё раз" не снимает „пройден" с этапа … день закрывается по первому естественному
 * концу». A talk walks the stage when it comes to an end of its OWN — the role's goodbye, the money, a refused subject
 * — and a talk cut by «Ещё раз» walks nothing. Catches a replayed talk counted as a walked stage, and a limit or a
 * declined talk that leaves the day waiting for a conversation that is over.
 */
it('walks the stage by an end of its own and never by being replayed', function () {
    foreach ([ConversationEnd::Natural, ConversationEnd::Limit, ConversationEnd::Declined] as $end) {
        $talk = convTalk();
        convAgent($talk);
        expect($talk->passesStage())->toBeFalse($end->value.' before the end');
        $talk->end($end, new DateTimeImmutable('2026-09-21T10:03:00Z'));
        expect($talk->passesStage())->toBeTrue($end->value);
    }

    $cut = convTalk();
    convAgent($cut);
    $cut->end(ConversationEnd::Replayed, new DateTimeImmutable('2026-09-21T10:03:00Z'));
    expect($cut->passesStage())->toBeFalse();
});

// The line a rescue asks to hear again is the role's line the learner's LAST move answers — what the guard of п. 4б
// compares the rescue with. Catches a guard that compares with the rescue's own reply or with the opening line.
it('knows which line of the role the learner\'s last move answers', function () {
    $talk = convTalk();
    expect($talk->lineBeforeLastMove())->toBeNull();

    convAgentAt($talk, '2026-09-21T10:00:00Z', 'Hello. What brings you in today?');
    convLearner($talk);
    convAgentAt($talk, '2026-09-21T10:00:20Z', 'How long has he had the fever?');
    convLearner($talk, TurnKind::Rescue);

    expect($talk->lineBeforeLastMove())->toBe('How long has he had the fever?');
});

/**
 * Canon (наряд CONV-1, п. 4 и кадр 37-12): «переспросы всегда нейтральны». A rescue costs a model call and a voice, so
 * it is spent from the MONEY — but it is not one of the day's three or four moves of the scene. Catches a «Не понял»
 * that eats a turn of the talk and shortens a day's conversation to two lines.
 */
it('spends a turn on a move of the scene and none on a rescue', function () {
    $talk = convTalk(turns: 3);
    convAgent($talk);

    expect($talk->turnsLeft())->toBe(3);

    convLearner($talk, TurnKind::Rescue);
    convAgent($talk);
    expect($talk->turnsLeft())->toBe(3);

    convLearner($talk, TurnKind::Said);
    convAgent($talk);
    convLearner($talk, TurnKind::Skip);
    convAgent($talk);
    expect($talk->turnsLeft())->toBe(1);
});

// Canon: the money cap is asked of ONE number — everything the talk has bought, the model and the voice together.
it('knows when it has spent what the plan allows it', function () {
    $talk = convTalk();
    convAgent($talk, cost: '0.030000');
    convLearner($talk);
    convAgent($talk, cost: '0.049000');

    expect($talk->costUsd())->toBe('0.079000')
        ->and($talk->overCap(0.08))->toBeFalse();

    convLearner($talk);
    convAgent($talk, cost: '0.001000');
    expect($talk->costUsd())->toBe('0.080000')->and($talk->overCap(0.08))->toBeTrue();
});

// Canon: the checkpoints are walked FORWARD; a scene marked done stays done, and an id the talk does not carry is
// ignored rather than trusted — the model names it, and the model is not the source of truth about the plan.
it('walks its scenes forward and ignores a scene that is not its own', function () {
    $talk = convTalk();

    expect($talk->currentCheckpoint())->toBe('s1');

    convAgent($talk, checkpoint: 's1');
    expect($talk->currentCheckpoint())->toBe('s2')
        ->and($talk->checkpointsDone())->toBe(['s1']);

    $talk->markCheckpoint('s1');
    $talk->markCheckpoint('somebody-elses-scene');
    expect($talk->checkpointsDone())->toBe(['s1']);

    convLearner($talk);
    convAgent($talk, checkpoint: 's2');
    expect($talk->currentCheckpoint())->toBeNull();
});

// Canon (кадр 37-7): «в режиме „Без подсказок" нет ни чипа, ни кнопки». Catches a hint that travels anyway and a hint
// shown while the role is still speaking.
it('offers the next intention only when hints are on and the move is the learner\'s', function () {
    $talk = convTalk();
    convAgent($talk, hint: 'скажи, что болит уже три дня');
    expect($talk->hintNative())->toBe('скажи, что болит уже три дня');

    convLearner($talk);
    expect($talk->hintNative())->toBeNull();

    $silent = convTalk(hints: false);
    convAgent($silent, hint: 'скажи, что болит');
    expect($silent->hintNative())->toBeNull();
});

/**
 * Canon (кадр 37-12; наряд CONV-2, п. 10): the summary is a PROJECTION of the journal over the talk's TARGETS — «сказал
 * сам» counts the moves with words in them, a rescue is asking to hear it again, «понял вопросы» counts only what the
 * role actually ruled on, and «фразы» are the targets the code heard. Catches a rescue counted as a line said by the
 * learner, a skip counted as a misunderstanding, and a phrase outside the targets counted into «X из Y».
 */
it('reads the summary off the journal: said, rescues, understood, and which targets did not sound', function () {
    $talk = convTalk();
    $targets = [
        new ConversationPhrase('s1', 'p1', 'It hurts in his ___.', 'У него болит ___.', 'lower back', 'поясница'),
        new ConversationPhrase('s1', 'p2', 'It started ___.', 'Началось ___.', 'three days ago', 'три дня назад'),
        new ConversationPhrase('s2', 'p1', 'Do we need ___?', 'Нам нужно ___?', 'an X-ray', 'рентген'),
    ];

    convAgent($talk);
    // s1:p9 is a phrase of the plan the code heard, but not one the talk asks for: it is on the ribbon, not in «X из Y».
    convLearner($talk, TurnKind::Said, ['s1:p2', 's1:p9']);
    convAgent($talk);
    convLearner($talk, TurnKind::Rescue);
    convAgent($talk);
    convLearner($talk, TurnKind::Skip);
    $talk->end(ConversationEnd::Natural, new DateTimeImmutable('2026-09-21T10:04:00Z'));

    $outcome = ConversationOutcomes::of($talk, $targets);

    expect($outcome->saidCount)->toBe(1)
        ->and($outcome->rescues)->toBe(1)
        ->and($outcome->phrasesUsed)->toBe(['s1:p2'])
        ->and($outcome->phrasesTotal)->toBe(3)
        ->and(array_values($outcome->notSaid))->toBe(['s1:p1', 's2:p1'])
        ->and($outcome->understoodAll)->toBeTrue()
        ->and($outcome->notUnderstood)->toBe(0)
        ->and($outcome->endedReason)->toBe(ConversationEnd::Natural)
        ->and($outcome->minutes)->toBe(1);
});

/**
 * Canon (наряд CONV-1, п. 4): «ушёл в сторону второй раз подряд — вернуть сразу», и на втором разговор кончается.
 * The streak is counted off the ROLE's lines, where its verdicts are written — the live run of the order found the
 * model not following «the second time» because it could not see there had been a first one.
 *
 * Catches a streak read off the learner's line (which carries no verdict), a streak broken by a rescue in between,
 * and a streak that keeps counting after the learner comes back to the scene.
 */
it('counts how many moves in a row went off the scene', function () {
    $talk = convTalk();
    convAgent($talk);
    expect($talk->offTopicStreak())->toBe(0);

    convLearner($talk);
    convAgent($talk, offTopic: true);
    expect($talk->offTopicStreak())->toBe(1);

    convLearner($talk);
    convAgent($talk, offTopic: true);
    expect($talk->offTopicStreak())->toBe(2);

    // Back to the scene: the streak is over, not merely paused.
    convLearner($talk);
    convAgent($talk, offTopic: false);
    expect($talk->offTopicStreak())->toBe(0);
});

// The rehearsal is the last talk before the event: nothing of it «comes back tomorrow» (кадр 37-12, «повтори перед приёмом»).
it('says which talks give their unsaid phrases back', function () {
    expect(ConversationType::Day->returnsTomorrow())->toBeTrue()
        ->and(ConversationType::Review->returnsTomorrow())->toBeTrue()
        ->and(ConversationType::Rehearsal->returnsTomorrow())->toBeFalse();
});
