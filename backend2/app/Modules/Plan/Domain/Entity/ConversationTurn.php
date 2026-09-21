<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
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
 * EACH LINE HOLDS WHAT ITS AUTHOR SAID. The learner's line holds the targets of the talk the SERVER
 * heard in it ({@see \App\Modules\Plan\Domain\Service\PhraseUse} — the code's rule first, the model's
 * word only as its second support). The role's reply holds what the ROLE judged about the move it answers — «понял ли он
 * вопрос», «увело ли в сторону» — because the role is who judged it, and the checkpoint it closed.
 * The hint is written on the line that OFFERS it: it is the intention the learner is shown for
 * their next move. A rescue and a skip are judged by nobody.
 *
 * Immutable on purpose, and WITHOUT `with…()` copies as well: a turn is an append-only journal row,
 * so there is no way — not even a well-meant one — to hand somebody a rewritten copy of a line that
 * has been stored. A line is built complete, from its words to its bill, and then written once.
 * What the server learns later about a talk lives on the talk.
 */
final readonly class ConversationTurn
{
    /** @param list<string> $phrasesUsed refs of the plan's phrases heard in this line (`p3`) */
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
    ) {}

    /**
     * The role's line: the opening one, an answer, the farewell. What the model judged about the
     * move it answers travels with it — the model is asked once and says both things at once.
     *
     * @param  list<string>  $phrasesUsed
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
        array $phrasesUsed = [],
        ?bool $offTopic = null,
    ): self {
        return new self(
            $id, $conversationId, $index, TurnKind::Agent, $textTarget, $textNative, $audio,
            $understood, $phrasesUsed, $offTopic, $checkpointDone, $hintNative, $cost, $now,
        );
    }

    /**
     * The learner's move. `said` carries what the recogniser heard; `rescue` and `skip` carry
     * nothing to judge — and nothing is judged on them ({@see TurnKind::isJudged()}).
     *
     * @param  list<string>  $phrasesUsed  what the SERVER heard by {@see \App\Modules\Plan\Domain\Service\PhraseUse} — the model's opinion alone never
     */
    public static function learner(
        ConversationTurnId $id,
        ConversationId $conversationId,
        int $index,
        TurnKind $kind,
        ?string $heard,
        array $phrasesUsed,
        DateTimeImmutable $now,
    ): self {
        return new self(
            $id, $conversationId, $index, $kind, $heard, null, null,
            null, $phrasesUsed, null, null, null, new TurnCost, $now,
        );
    }

    /** @param list<string> $phrasesUsed */
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
    ): self {
        return new self(
            $id, $conversationId, $index, $kind, $textTarget, $textNative, $audio,
            $understood, $phrasesUsed, $offTopic, $checkpointDone, $hintNative, $cost, $createdAt,
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
}
