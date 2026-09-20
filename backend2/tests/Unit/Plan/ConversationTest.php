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

function convLearner(Conversation $talk, TurnKind $kind = TurnKind::Said, array $phrases = []): void
{
    $talk->recordLearnerTurn(ConversationTurn::learner(
        id: ConversationTurnId::generate(),
        conversationId: $talk->id(),
        index: $talk->nextIndex(),
        kind: $kind,
        heard: $kind === TurnKind::Said ? 'It started three days ago.' : null,
        phrasesUsed: $phrases,
        now: new DateTimeImmutable('2026-09-21T10:00:10Z'),
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
        ->and($talk->minutes())->toBe(3)
        ->and(fn () => convLearner($talk))->toThrow(ConversationEnded::class)
        ->and(fn () => convAgent($talk))->toThrow(ConversationEnded::class);
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
 * Canon (кадр 37-12): the summary is a PROJECTION of the journal — «сказал сам» counts the moves with words in them, a
 * rescue is asking to hear it again, and «понял вопросы» counts only what the role actually ruled on. Catches a rescue
 * counted as a line said by the learner and a skip counted as a misunderstanding.
 */
it('reads the summary off the journal: said, rescues, understood, and what did not sound', function () {
    $talk = convTalk();
    $phrases = [
        new ConversationPhrase('s1', 'p1', 'It hurts in his', 'It hurts in his ___.', 'У него болит ___.'),
        new ConversationPhrase('s1', 'p2', 'It started', 'It started ___.', 'Началось ___.'),
        new ConversationPhrase('s2', 'p1', 'Do we need', 'Do we need ___?', 'Нам нужно ___?'),
    ];

    convAgent($talk);
    convLearner($talk, TurnKind::Said, ['s1:p2']);
    convAgent($talk);
    convLearner($talk, TurnKind::Rescue);
    convAgent($talk);
    convLearner($talk, TurnKind::Skip);
    $talk->end(ConversationEnd::Natural, new DateTimeImmutable('2026-09-21T10:04:00Z'));

    $outcome = ConversationOutcomes::of($talk, $phrases);

    expect($outcome->saidCount)->toBe(1)
        ->and($outcome->rescues)->toBe(1)
        ->and($outcome->phrasesUsed)->toBe(['s1:p2'])
        ->and($outcome->phrasesTotal)->toBe(3)
        ->and(array_values($outcome->notSaid))->toBe(['s1:p1', 's2:p1'])
        ->and($outcome->understoodAll)->toBeTrue()
        ->and($outcome->notUnderstood)->toBe(0)
        ->and($outcome->endedReason)->toBe(ConversationEnd::Natural)
        ->and($outcome->minutes)->toBe(4);
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
