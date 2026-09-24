<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\SceneEvent;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\TurnAudio;
use App\Modules\Plan\Domain\ValueObject\TurnCost;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use DateTimeImmutable;

/**
 * ONE LINE OF THE TALK — written once and never changed (наряд CONV-1, п. 2).
 *
 * Both speakers keep their lines in the same list, in the order they were said, because that is
 * what the ribbon (кадры 37-6…37-12) draws and what the next model call is shown.
 *
 * EACH LINE HOLDS WHAT ITS AUTHOR SAID, AND THE SCENE IT WAS SAID IN (`sceneId`, наряд FIX-4 §4). The learner's line holds
 * the constructions of its scene the SERVER heard in it — said (`phrasesUsed`: the talk's targets and the scene's other
 * frames, «ещё вспомнил») and said almost (`phrasesAlmost`) — by the judge ({@see \App\Modules\Plan\Domain\Service\FrameJudge}),
 * which reads the move alone: it is judged before it is written, whole. The role's reply holds what the ROLE judged about
 * the move it answers — «понял ли он вопрос», «увело ли в сторону» — the door it opened (`opensTarget`, as the server
 * accepted it), the hint it offers the learner for their next move, the checkpoint it closed, and — in a talk over several
 * scenes — whether it begins its scene or ends it (`sceneEvent`). A rescue and a skip are judged by nobody.
 *
 * Immutable on purpose, and WITHOUT `with…()` copies as well: a turn is an append-only journal row,
 * so there is no way — not even a well-meant one — to hand somebody a rewritten copy of a line that
 * has been stored. A line is built complete, from its words to its bill, and then written once.
 * What the server learns later about a talk lives on the talk.
 */
final readonly class ConversationTurn
{
    /**
     * @param  list<string>  $phrasesUsed  the ids (`<scene>:<ref>`) of the constructions this line said
     * @param  list<string>  $phrasesAlmost  the ids of those it said almost
     */
    private function __construct(
        public ConversationTurnId $id,
        public ConversationId $conversationId,
        public int $index,
        public TurnKind $kind,
        public ?string $textTarget,
        public ?string $textNative,
        public ?TurnAudio $audio,
        public ?bool $understood,
        public array $phrasesUsed,
        public ?bool $offTopic,
        public ?string $checkpointDone,
        public ?string $hintNative,
        public TurnCost $cost,
        public DateTimeImmutable $createdAt,
        public ?string $opensTarget = null,
        public ?string $sceneId = null,
        public ?SceneEvent $sceneEvent = null,
        public array $phrasesAlmost = [],
    ) {}

    /**
     * The role's line: the opening one, an answer, a scene's goodbye and the next role's greeting. What the model judged
     * about the move it answers travels with it — the model is asked once and says both things at once — and so does the
     * target the line OPENS THE DOOR TO (`$opensTarget`, наряд FIX-3 §7; accepted by the server, наряд FIX-4 §3): the one
     * the learner could say next in answer to it.
     */
    public static function agent(
        ConversationTurnId $id,
        ConversationId $conversationId,
        int $index,
        string $textTarget,
        string $textNative,
        ?TurnAudio $audio,
        ?string $checkpointDone,
        ?string $hintNative,
        TurnCost $cost,
        DateTimeImmutable $now,
        ?bool $understood = null,
        ?bool $offTopic = null,
        ?string $opensTarget = null,
        ?string $sceneId = null,
        ?SceneEvent $sceneEvent = null,
    ): self {
        return new self(
            $id, $conversationId, $index, TurnKind::Agent, $textTarget, $textNative, $audio,
            $understood, [], $offTopic, $checkpointDone, $hintNative, $cost, $now, $opensTarget, $sceneId, $sceneEvent,
        );
    }

    /**
     * The learner's move. `said` carries what the recogniser heard and what the server heard in it; `rescue` and `skip`
     * carry nothing to judge — and nothing is judged on them ({@see TurnKind::isJudged()}).
     *
     * @param  list<string>  $phrasesUsed  the constructions of its scene the move said, by the judge
     * @param  list<string>  $phrasesAlmost  those it said almost
     */
    public static function learner(
        ConversationTurnId $id,
        ConversationId $conversationId,
        int $index,
        TurnKind $kind,
        ?string $heard,
        array $phrasesUsed,
        DateTimeImmutable $now,
        array $phrasesAlmost = [],
        ?string $sceneId = null,
    ): self {
        return new self(
            $id, $conversationId, $index, $kind, $heard, null, null,
            null, $phrasesUsed, null, null, null, new TurnCost, $now, null, $sceneId, null, $phrasesAlmost,
        );
    }

    /**
     * @param  list<string>  $phrasesUsed
     * @param  list<string>  $phrasesAlmost
     */
    public static function reconstitute(
        ConversationTurnId $id,
        ConversationId $conversationId,
        int $index,
        TurnKind $kind,
        ?string $textTarget,
        ?string $textNative,
        ?TurnAudio $audio,
        ?bool $understood,
        array $phrasesUsed,
        ?bool $offTopic,
        ?string $checkpointDone,
        ?string $hintNative,
        TurnCost $cost,
        DateTimeImmutable $createdAt,
        ?string $opensTarget = null,
        ?string $sceneId = null,
        ?SceneEvent $sceneEvent = null,
        array $phrasesAlmost = [],
    ): self {
        return new self(
            $id, $conversationId, $index, $kind, $textTarget, $textNative, $audio,
            $understood, $phrasesUsed, $offTopic, $checkpointDone, $hintNative, $cost, $createdAt, $opensTarget,
            $sceneId, $sceneEvent, $phrasesAlmost,
        );
    }

    public function speaker(): Speaker
    {
        return $this->kind->speaker();
    }

    /** Did the learner say something of their own here — what «Сказал сам N реплик» counts. */
    public function isSpokenByLearner(): bool
    {
        return $this->kind === TurnKind::Said && trim((string) $this->textTarget) !== '';
    }

    /** Is this a move of the learner that spends one of the talk's moves — said or let go; a rescue spends none. */
    public function isMove(): bool
    {
        return $this->kind === TurnKind::Said || $this->kind === TurnKind::Skip;
    }
}
