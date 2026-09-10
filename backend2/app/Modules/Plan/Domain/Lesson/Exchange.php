<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * One exchange of the visit: two messages and the question about what A said. The model may
 * hand back any number of messages; the checks decide what to do with the wrong count, so the
 * value object keeps whatever arrived and offers the two views the day is assembled from.
 */
final readonly class Exchange
{
    /** @param list<Message> $messages */
    public function __construct(
        public int $step,
        public string $initiator,
        public array $messages,
        public Question $question,
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
        return new self($this->step, $this->initiator, $messages, $this->question);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'initiator' => $this->initiator,
            'messages' => array_map(static fn (Message $m): array => $m->toArray(), $this->messages),
            'question' => $this->question->toArray(),
        ];
    }
}
