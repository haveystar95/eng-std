<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * ONE EXCHANGE AS THE DIALOGUE WRITES IT (`lesson_dialogue.v1.1`, EXCHANGES; наряд GEN-4): the lesson's exchange ({@see Exchange}
 * — step, kind, who opens it, two messages, the check) and which partner line of the skeleton A says in it — its id
 * (`partner_line`, `a3`) and the `must_understand` item it delivers; both null for a rescue and for an A line the dialogue
 * wrote itself (the one A line of a frame no partner line pairs with). The lesson the learner gets carries the exchange
 * without them.
 */
final readonly class DialogueExchange
{
    public function __construct(
        public Exchange $exchange,
        public ?int $mustUnderstand,
        public ?string $partnerLine,
    ) {}

    public function step(): int
    {
        return $this->exchange->step;
    }

    public function withExchange(Exchange $exchange): self
    {
        return new self($exchange, $this->mustUnderstand, $this->partnerLine);
    }

    /**
     * The exchange in the dialogue's own shape — the prompt's key order: step, kind, initiator, must_understand, partner_line,
     * messages, check.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $exchange = $this->exchange->toArray();

        return [
            'step' => $exchange['step'],
            'kind' => $exchange['kind'],
            'initiator' => $exchange['initiator'],
            'must_understand' => $this->mustUnderstand,
            'partner_line' => $this->partnerLine,
            'messages' => $exchange['messages'],
            'check' => $exchange['check'],
        ];
    }
}
