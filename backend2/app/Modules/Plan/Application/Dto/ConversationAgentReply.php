<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\TurnCost;

/**
 * ONE ANSWER OF THE ROLE, checked into shape (наряд CONV-1, п. 4). The schema is strict at the
 * vendor, and this is the second reading: a payload that is off the shape here is a silent agent,
 * not a turn — the ribbon keeps its place and the learner is offered «Повторить» (кадр 37-10).
 */
final readonly class ConversationAgentReply
{
    public const END_NO = 'no';

    public const END_NATURAL = 'natural';

    public const END_DECLINED = 'declined';

    /** @param list<string> $phrasesUsed the model's own opinion — kept for the report, never for the score */
    public function __construct(
        public string $replyTarget,
        public string $replyNative,
        public ?bool $understood,
        public array $phrasesUsed,
        public bool $offTopic,
        public ?string $checkpointDone,
        public ?string $nextHintNative,
        public string $end,
        public TurnCost $cost,
    ) {}

    public function endsTalk(): bool
    {
        return $this->end !== self::END_NO;
    }

    /** This answer with its texts cut down to the role's own sentences (наряд CONV-2, {@see \App\Modules\Plan\Domain\Service\RoleLines::withoutLearnerLines()}). */
    public function saying(string $target, string $native): self
    {
        return new self(
            $target, $native, $this->understood, $this->phrasesUsed, $this->offTopic,
            $this->checkpointDone, $this->nextHintNative, $this->end, $this->cost,
        );
    }

    /** This answer, billed for the refused one before it as well (наряд CONV-2). */
    public function billedWith(TurnCost $refused): self
    {
        return new self(
            $this->replyTarget, $this->replyNative, $this->understood, $this->phrasesUsed, $this->offTopic,
            $this->checkpointDone, $this->nextHintNative, $this->end, $refused->plusModelCall($this->cost),
        );
    }

    public function isDeclined(): bool
    {
        return $this->end === self::END_DECLINED;
    }
}
