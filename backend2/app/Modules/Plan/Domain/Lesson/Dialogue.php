<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * THE DIALOGUE OF A DAY (`lesson_dialogue.v1.1`, наряд GEN-4) — the second of the day's two stages: the skeleton's frames and
 * partner lines put into DIALOGUE_COUNT exchanges, a check in every exchange, and the listening questions of the whole
 * visit. It adds no frame, no partner line, no fact and no word: what it may write itself is the glue of a learner line, the
 * rescue, the one A line of a frame no partner line pairs with, the checks, the listening and the speaking support.
 *
 * Immutable. Parsed by {@see LessonParser::dialogue()}; assembled with its skeleton into the lesson ({@see LessonAssembler}).
 */
final readonly class Dialogue
{
    /**
     * @param  list<DialogueExchange>  $exchanges
     * @param  list<ListeningQuestion>  $listening
     */
    public function __construct(
        public array $exchanges,
        public array $listening,
    ) {}

    public function exchange(int $step): ?DialogueExchange
    {
        foreach ($this->exchanges as $exchange) {
            if ($exchange->step() === $step) {
                return $exchange;
            }
        }

        return null;
    }

    /** @return list<DialogueExchange> the exchanges that carry the partner line `$id` */
    public function carrying(string $id): array
    {
        return array_values(array_filter($this->exchanges, static fn (DialogueExchange $e): bool => $e->partnerLine === $id));
    }

    /** @param list<DialogueExchange> $exchanges */
    public function withExchanges(array $exchanges): self
    {
        return new self($exchanges, $this->listening);
    }

    /** @param list<ListeningQuestion> $listening */
    public function withListening(array $listening): self
    {
        return new self($this->exchanges, $listening);
    }

    /**
     * The partner line `$line` said anew in every exchange that carries it — A's message takes the line's two texts (наряд
     * GEN-4, 3.9: after a repair of a partner line the program puts it into its exchange; the repair never returns it).
     */
    public function withPartnerLine(PartnerLine $line): self
    {
        return $this->withExchanges(array_map(static function (DialogueExchange $e) use ($line): DialogueExchange {
            if ($e->partnerLine !== $line->id) {
                return $e;
            }

            return $e->withExchange($e->exchange->withMessages(array_map(
                static fn (Message $m): Message => $m->isLearner() ? $m : $m->withTexts($line->textTarget, $line->textNative),
                $e->exchange->messages,
            )));
        }, $this->exchanges));
    }

    /**
     * The shape of the prompt's OUTPUT SCHEMA, keys in its order — what a repair of a dialogue card reads as DIALOGUE.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dialogue' => array_map(static fn (DialogueExchange $e): array => $e->toArray(), $this->exchanges),
            'listening' => [
                'questions' => array_map(static fn (ListeningQuestion $q): array => $q->toArray(), $this->listening),
            ],
        ];
    }
}
