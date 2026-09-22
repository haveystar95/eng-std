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

    /**
     * @param  list<string>  $phrasesUsed  the model's own opinion — the second support of the code's rule, never the first
     * @param  string|null  $opens  the target the reply opens the door to, as the model named it (наряд FIX-3 §7)
     */
    public function __construct(
        public string $replyTarget,
        public string $replyNative,
        public ?bool $understood,
        public array $phrasesUsed,
        public bool $offTopic,
        public ?string $checkpointDone,
        public ?string $opens,
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
            $this->checkpointDone, $this->opens, $this->end, $this->cost,
        );
    }

    /** This answer with no judgement of whether the move was understood — a move that broke off (наряд FIX-3 §7). */
    public function unjudged(): self
    {
        return new self(
            $this->replyTarget, $this->replyNative, null, $this->phrasesUsed, $this->offTopic,
            $this->checkpointDone, $this->opens, $this->end, $this->cost,
        );
    }

    /** This answer, billed for the refused one before it as well (наряд CONV-2). */
    public function billedWith(TurnCost $refused): self
    {
        return new self(
            $this->replyTarget, $this->replyNative, $this->understood, $this->phrasesUsed, $this->offTopic,
            $this->checkpointDone, $this->opens, $this->end, $refused->plusModelCall($this->cost),
        );
    }

    public function isDeclined(): bool
    {
        return $this->end === self::END_DECLINED;
    }
}
