<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * One exchange of the visit: its kind, who opens it, two messages and the check of what the partner
 * said. The model may hand back any number of messages; the validator counts a wrong shape, so the
 * value object keeps whatever arrived and offers the views the day is assembled from.
 */
final readonly class Exchange
{
    /** @param list<Message> $messages */
    public function __construct(
        public int $step,
        public ExchangeKind $kind,
        public string $initiator,
        public array $messages,
        public ExchangeCheck $check,
    ) {}

    public function first(): ?Message
    {
        return $this->messages[0] ?? null;
    }

    public function second(): ?Message
    {
        return $this->messages[1] ?? null;
    }

    /** The partner's line — what the learner listens to. */
    public function partner(): ?Message
    {
        foreach ($this->messages as $message) {
            if (! $message->isLearner()) {
                return $message;
            }
        }

        return null;
    }

    /** The learner's line — what they say. */
    public function learner(): ?Message
    {
        foreach ($this->messages as $message) {
            if ($message->isLearner()) {
                return $message;
            }
        }

        return null;
    }

    public function partnerStarts(): bool
    {
        return $this->initiator === Message::SPEAKER_PARTNER;
    }

    /** @param list<Message> $messages */
    public function withMessages(array $messages): self
    {
        return new self($this->step, $this->kind, $this->initiator, $messages, $this->check);
    }

    public function withCheck(ExchangeCheck $check): self
    {
        return new self($this->step, $this->kind, $this->initiator, $this->messages, $check);
    }

    /** The same exchange at another step — a repaired exchange keeps the place of the one it replaces. */
    public function withStep(int $step): self
    {
        return new self($step, $this->kind, $this->initiator, $this->messages, $this->check);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'kind' => $this->kind->value,
            'initiator' => $this->initiator,
            'messages' => array_map(static fn (Message $m): array => $m->toArray(), $this->messages),
            'check' => $this->check->toArray(),
        ];
    }
}
