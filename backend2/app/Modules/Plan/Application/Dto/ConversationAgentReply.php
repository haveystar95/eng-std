<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\TurnCost;

/**
 * ONE ANSWER OF THE ROLE, checked into shape (наряд CONV-1, п. 4). The schema is strict at the
 * vendor, and this is the second reading: a payload that is off the shape here is a silent agent,
 * not a turn — the ribbon keeps its place and the learner is offered «Повторить» (кадр 37-10).
 *
 * `opens` is the door as the role NAMED it — a short id of a target (`T3`), or anything else a model may say: which
 * target it stands for, and whether that is a door of the scene at all, is the server's to decide (наряд FIX-4 §3). The
 * role is not asked which constructions were said nor whether its scene is over (`conversation_agent.v3.2`, наряд FIX-4b
 * §3): the judge is the code's (наряд FIX-4 §2) and so are the scenes (§4). `callId` is the row of `model_calls` the
 * answer came from.
 */
final readonly class ConversationAgentReply
{
    public const END_NO = 'no';

    public const END_NATURAL = 'natural';

    public const END_DECLINED = 'declined';

    public function __construct(
        public string $replyTarget,
        public string $replyNative,
        public ?bool $understood,
        public bool $offTopic,
        public ?string $opens,
        public string $end,
        public TurnCost $cost,
        public ?string $callId = null,
    ) {}

    public function endsTalk(): bool
    {
        return $this->end !== self::END_NO;
    }

    /** This answer with its texts cut down to the role's own sentences (наряд CONV-2, {@see \App\Modules\Plan\Domain\Service\RoleLines::withoutLearnerLines()}). */
    public function saying(string $target, string $native): self
    {
        return new self($target, $native, $this->understood, $this->offTopic, $this->opens, $this->end, $this->cost, $this->callId);
    }

    /** This answer with no judgement of whether the move was understood — a move that broke off (наряд FIX-3 §7). */
    public function unjudged(): self
    {
        return new self($this->replyTarget, $this->replyNative, null, $this->offTopic, $this->opens, $this->end, $this->cost, $this->callId);
    }

    /** This answer, billed for the refused one before it as well (наряд CONV-2). */
    public function billedWith(TurnCost $refused): self
    {
        return new self(
            $this->replyTarget, $this->replyNative, $this->understood, $this->offTopic, $this->opens, $this->end,
            $refused->plusModelCall($this->cost), $this->callId,
        );
    }

    public function isDeclined(): bool
    {
        return $this->end === self::END_DECLINED;
    }
}
