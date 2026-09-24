<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\Exception\ConversationEnded;
use App\Modules\Plan\Domain\Exception\ConversationNotYourTurn;
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationRejection;
use App\Modules\Plan\Domain\ValueObject\ConversationState;
use App\Modules\Plan\Domain\ValueObject\ConversationType;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use LogicException;

/**
 * THE TALK WITH THE AGENT (наряд CONV-1) — a journal with a state machine around it.
 *
 * It is not the «Диалог» trainer: nothing here is prepared. The role opens, the learner says
 * whatever they say, and the role answers THAT. What the aggregate protects is everything that
 * could be broken from outside:
 *
 * - the turns are APPEND-ONLY and numbered by the aggregate — a client cannot insert, reorder or
 *   rewrite a line of the ribbon it is shown;
 * - a move may only be made when it is the learner's move: a second `POST …/turn` sent while the
 *   first is still in flight is refused, not run twice;
 * - an ended talk takes nothing more. «Ещё раз» is a NEW talk ({@see ConversationEnd::Replayed}),
 *   never this one reopened, so the day keeps every word that was actually said;
 * - the checkpoints are walked FORWARD: a scene marked done stays done, and the current one is the
 *   first that is not — which is what the hint and the summary read;
 * - the money is added up here and nowhere else, so the cap is asked of one number.
 *
 * «Ходы» and «money» are two different budgets on purpose. A rescue («Не понял») costs a model call
 * and a voice, so it is spent from the money — but it is NOT one of the day's three or four turns:
 * asking the role to repeat itself is not a move of the scene, and «переспросы всегда нейтральны»
 * (кадр 37-12). A skip is a move: the learner let it go, the scene carries on.
 *
 * THE SCENES ARE THE SERVER'S (наряд FIX-4 §4): every line carries the scene it was said in, and in a talk over several
 * scenes the scene closes by the server's rule — its moves counted here ({@see movesIn()}), its checkpoint marked on the
 * role's goodbye. What the server refused of the role's answers on the way is kept too ({@see reject()}): a journal of its
 * own, append-only, written with the move.
 */
final class Conversation
{
    /** The most one gap between two lines counts for in the talk's minutes (наряд CONV-2, п. 3). */
    public const MAX_GAP_SECONDS = 60;

    /** @var list<ConversationRejection> what the server refused of the role's answers in THIS process — written with the move */
    private array $rejections = [];

    /**
     * @param  list<string>  $sceneIds  the checkpoints, in the order the talk walks them
     * @param  list<string>  $checkpointsDone
     * @param  list<ConversationTurn>  $turns
     */
    private function __construct(
        private readonly ConversationId $id,
        private readonly PlanId $planId,
        private readonly UserId $userId,
        private readonly PlanDayId $dayId,
        private readonly int $dayNumber,
        private readonly ConversationType $type,
        private readonly array $sceneIds,
        private array $checkpointsDone,
        private ConversationState $state,
        private readonly int $turnLimit,
        private readonly bool $hintsEnabled,
        private array $turns,
        private string $costUsd,
        private readonly DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $endedAt,
        private ?ConversationEnd $endedReason,
    ) {}

    /**
     * A talk about to begin: the role's opening line is written by the server right after this, so
     * it starts on the agent's move.
     *
     * @param  list<string>  $sceneIds
     */
    public static function start(
        ConversationId $id,
        PlanId $planId,
        UserId $userId,
        PlanDayId $dayId,
        int $dayNumber,
        ConversationType $type,
        array $sceneIds,
        int $turnLimit,
        bool $hintsEnabled,
        DateTimeImmutable $now,
    ): self {
        return new self(
            $id, $planId, $userId, $dayId, $dayNumber, $type, $sceneIds, [],
            ConversationState::AgentTurn, max(1, $turnLimit), $hintsEnabled, [], '0.000000', $now, null, null,
        );
    }

    /**
     * @param  list<string>  $sceneIds
     * @param  list<string>  $checkpointsDone
     * @param  list<ConversationTurn>  $turns
     */
    public static function reconstitute(
        ConversationId $id,
        PlanId $planId,
        UserId $userId,
        PlanDayId $dayId,
        int $dayNumber,
        ConversationType $type,
        array $sceneIds,
        array $checkpointsDone,
        ConversationState $state,
        int $turnLimit,
        bool $hintsEnabled,
        array $turns,
        string $costUsd,
        DateTimeImmutable $startedAt,
        ?DateTimeImmutable $endedAt,
        ?ConversationEnd $endedReason,
    ): self {
        return new self(
            $id, $planId, $userId, $dayId, $dayNumber, $type, $sceneIds, $checkpointsDone,
            $state, $turnLimit, $hintsEnabled, $turns, $costUsd, $startedAt, $endedAt, $endedReason,
        );
    }

    /** The next line's number — the aggregate hands it out, so the journal cannot be written into sideways. */
    public function nextIndex(): int
    {
        return count($this->turns) + 1;
    }

    /**
     * The learner's move, complete — its words and what the server heard in them: refused unless it IS their move, and
     * refused outright once the talk is over.
     */
    public function recordLearnerTurn(ConversationTurn $turn): void
    {
        $this->assertOpen();
        if ($this->state !== ConversationState::YourTurn) {
            throw ConversationNotYourTurn::state($this->state);
        }
        if ($turn->kind === TurnKind::Agent) {
            throw new LogicException('A line of the role is not a move of the learner.');
        }
        $this->turns[] = $turn;
        $this->state = ConversationState::AgentTurn;
    }

    /**
     * The role's line. It leaves the move with the learner — unless the talk is closed after it; a scene's goodbye and the
     * next role's greeting are two lines in a row, and the learner answers the second.
     */
    public function recordAgentTurn(ConversationTurn $turn): void
    {
        $this->assertOpen();
        $this->turns[] = $turn;
        if ($turn->checkpointDone !== null) {
            $this->markCheckpoint($turn->checkpointDone);
        }
        $this->state = ConversationState::YourTurn;
    }

    /** Something the server refused of an answer of the role on this move — journaled with it. */
    public function reject(ConversationRejection $rejection): void
    {
        $this->rejections[] = $rejection;
    }

    /** @return list<ConversationRejection> what was refused in this process, not yet written */
    public function rejections(): array
    {
        return $this->rejections;
    }

    /**
     * HOW MANY MOVES THE LEARNER HAS MADE IN A SCENE (наряд FIX-4 §4) — what the scene's budget is spent by: a move said or
     * let go in it; a rescue is no move.
     */
    public function movesIn(string $sceneId): int
    {
        $moves = 0;
        foreach ($this->turns as $turn) {
            if ($turn->isMove() && $turn->sceneId === $sceneId) {
                $moves++;
            }
        }

        return $moves;
    }

    /**
     * DID THE TALK END BECAUSE A LIMIT RAN OUT (наряд FIX-4 §4) — its money or its minutes (`ended_reason: limit`), or its
     * moves before its scenes were walked: the last line of the role was a scene's goodbye the server asked for, and a
     * scene of the talk has no checkpoint. `ended_reason` keeps its values (the phone's build (20) reads them): this is a
     * flag beside it. A talk that has not ended — false.
     */
    public function endedByLimit(): bool
    {
        return ConversationOutcomes::endedByLimit(
            $this->state === ConversationState::Ended,
            $this->endedReason?->value,
            $this->lastAgentTurn()?->sceneEvent?->value,
            array_diff($this->sceneIds, $this->checkpointsDone) === [],
        );
    }

    public function lastAgentTurn(): ?ConversationTurn
    {
        for ($i = count($this->turns) - 1; $i >= 0; $i--) {
            if ($this->turns[$i]->kind === TurnKind::Agent) {
                return $this->turns[$i];
            }
        }

        return null;
    }

    public function end(ConversationEnd $reason, DateTimeImmutable $now): void
    {
        if ($this->state === ConversationState::Ended) {
            return;
        }
        $this->state = ConversationState::Ended;
        $this->endedReason = $reason;
        $this->endedAt = $now;
    }

    /** Money spent on one move — model plus voice; the cap is asked of the sum and nothing else. */
    public function spend(string $usd): void
    {
        $this->costUsd = ModelCall::addCosts($this->costUsd, $usd);
    }

    /** A scene is walked; it stays walked. An id the talk does not carry is ignored, not trusted. */
    public function markCheckpoint(string $sceneId): void
    {
        if (in_array($sceneId, $this->sceneIds, true) && ! in_array($sceneId, $this->checkpointsDone, true)) {
            $this->checkpointsDone[] = $sceneId;
        }
    }

    /** The scene the talk is on now — the first checkpoint not walked, or null when all of them are. */
    public function currentCheckpoint(): ?string
    {
        foreach ($this->sceneIds as $sceneId) {
            if (! in_array($sceneId, $this->checkpointsDone, true)) {
                return $sceneId;
            }
        }

        return null;
    }

    /**
     * How many moves of the talk are left. A rescue is not one of them (see the class note), so
     * only what the learner SAID or let go counts against the limit.
     */
    public function turnsLeft(): int
    {
        $spent = 0;
        foreach ($this->turns as $turn) {
            if ($turn->isMove()) {
                $spent++;
            }
        }

        return max(0, $this->turnLimit - $spent);
    }

    /**
     * HOW MANY OF THE LEARNER'S LAST MOVES IN A ROW WENT OFF THE SCENE — counted off the ROLE's own
     * lines, which is where its verdicts are written. Two things read it: the prompt (a rule about
     * «the second time» is a rule the model cannot follow without knowing there was a first), and
     * the close of a talk the learner pushed off the scene twice.
     */
    public function offTopicStreak(): int
    {
        $streak = 0;
        for ($i = count($this->turns) - 1; $i >= 0; $i--) {
            if ($this->turns[$i]->kind !== TurnKind::Agent) {
                continue;
            }
            if ($this->turns[$i]->offTopic !== true) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /** Has the talk spent the money the plan allows it (`plan.conversation.cost_cap_usd`)? */
    public function overCap(float $capUsd): bool
    {
        return $capUsd > 0.0 && (float) $this->costUsd >= $capUsd;
    }

    /** @return list<ConversationTurn> */
    public function turns(): array
    {
        return $this->turns;
    }

    /**
     * The targets the role's lines have opened the door to so far (наряд FIX-3 §7), in the order they were opened.
     *
     * @return list<string>
     */
    public function openedDoors(): array
    {
        $out = [];
        foreach ($this->turns as $turn) {
            if ($turn->kind === TurnKind::Agent && $turn->opensTarget !== null) {
                $out[] = $turn->opensTarget;
            }
        }

        return array_values(array_unique($out));
    }

    public function lastTurn(): ?ConversationTurn
    {
        return $this->turns === [] ? null : $this->turns[count($this->turns) - 1];
    }

    /** Is it the learner's move now — the talk open and waiting for them? */
    public function awaitsLearner(): bool
    {
        return $this->state === ConversationState::YourTurn;
    }

    public function isEnded(): bool
    {
        return $this->state === ConversationState::Ended;
    }

    public function id(): ConversationId
    {
        return $this->id;
    }

    public function planId(): PlanId
    {
        return $this->planId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function dayId(): PlanDayId
    {
        return $this->dayId;
    }

    public function dayNumber(): int
    {
        return $this->dayNumber;
    }

    public function type(): ConversationType
    {
        return $this->type;
    }

    /** @return list<string> */
    public function sceneIds(): array
    {
        return $this->sceneIds;
    }

    /** @return list<string> */
    public function checkpointsDone(): array
    {
        return $this->checkpointsDone;
    }

    public function state(): ConversationState
    {
        return $this->state;
    }

    public function turnLimit(): int
    {
        return $this->turnLimit;
    }

    public function hintsEnabled(): bool
    {
        return $this->hintsEnabled;
    }

    public function costUsd(): string
    {
        return $this->costUsd;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function endedReason(): ?ConversationEnd
    {
        return $this->endedReason;
    }

    /**
     * HOW LONG THE TALK WAS TALKED, once it is over — «Разговор окончен · 3 минуты» — in whole minutes, never less than
     * one ({@see activeSeconds()}).
     */
    public function minutes(): ?int
    {
        if ($this->endedAt === null) {
            return null;
        }

        return max(1, (int) ceil($this->activeSeconds() / 60));
    }

    /**
     * THE TIME OF THE TALK, NOT OF THE CLOCK (наряд CONV-2, п. 3): the gaps between neighbouring lines of the journal,
     * each counted up to {@see MAX_GAP_SECONDS}. A talk begun in the morning and finished after lunch was «323 минуты»
     * on the phone (CLIENT-CONV-1a, §5 п. 12) — the wall clock, not the conversation; a gap longer than a minute is
     * the learner away from it, and a minute is what it is allowed to count for.
     */
    public function activeSeconds(): int
    {
        $seconds = 0;
        $previous = null;
        foreach ($this->turns as $turn) {
            if ($previous !== null) {
                $gap = $turn->createdAt->getTimestamp() - $previous->createdAt->getTimestamp();
                $seconds += max(0, min(self::MAX_GAP_SECONDS, $gap));
            }
            $previous = $turn;
        }

        return $seconds;
    }

    /**
     * DID THIS TALK WALK THE DAY'S SIXTH STAGE (наряд CONV-2, п. 2): it came to an end of its own — the role said goodbye,
     * the money ran out, or a refused subject was pushed twice. A talk closed by «Ещё раз» did not end, it was cut; it
     * walks nothing. Which talk of the day walked the stage is a fact written once ({@see \App\Modules\Plan\Domain\ValueObject\StagePassage}).
     */
    public function passesStage(): bool
    {
        return $this->state === ConversationState::Ended && $this->endedReason !== null && $this->endedReason !== ConversationEnd::Replayed;
    }

    /**
     * The role's line the learner's LAST move answers — what a rescue asks to hear again. Null before the role has said
     * anything.
     */
    public function lineBeforeLastMove(): ?string
    {
        $seenMove = false;
        for ($i = count($this->turns) - 1; $i >= 0; $i--) {
            $turn = $this->turns[$i];
            if ($turn->kind !== TurnKind::Agent) {
                $seenMove = true;

                continue;
            }
            if ($seenMove) {
                return $turn->textTarget;
            }
        }

        return null;
    }

    private function assertOpen(): void
    {
        if ($this->state === ConversationState::Ended) {
            throw ConversationEnded::talk($this->id);
        }
    }
}
