<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * The lesson of one scene (`lesson_day.v4.5`): the visit as exchanges, the frames the learner's
 * lines stand on, the questions about the whole visit, and the day's words.
 *
 * Immutable. Two of them exist per scene: the ANSWER — the model's JSON as written, what is stored
 * and what the validator judges — and the SERVED lesson every reader deals from, where a framed
 * learner line is the server's own assembly and the right answers stand at shuffled places
 * ({@see LessonAssembly}). Parsed by {@see LessonParser}; a lesson that cannot be parsed never
 * exists as a value.
 */
final readonly class Lesson
{
    /**
     * @param  list<Exchange>  $exchanges
     * @param  list<Phrase>  $phrases
     * @param  list<ListeningQuestion>  $listening
     * @param  list<VocabularyItem>  $vocabulary
     * @param  VoiceGender|null  $roleGender  the partner's gender as the lesson imagines the role; null when the answer named an odd word
     */
    public function __construct(
        public string $titleTarget,
        public string $titleNative,
        public string $descriptionTarget,
        public string $descriptionNative,
        public string $learnerRoleTarget,
        public string $learnerRoleNative,
        public ?VoiceGender $roleGender,
        public array $exchanges,
        public array $phrases,
        public array $listening,
        public array $vocabulary,
    ) {}

    public function phrase(string $id): ?Phrase
    {
        foreach ($this->phrases as $phrase) {
            if ($phrase->id === $id) {
                return $phrase;
            }
        }

        return null;
    }

    public function vocabularyItem(string $id): ?VocabularyItem
    {
        foreach ($this->vocabulary as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    public function exchange(int $step): ?Exchange
    {
        foreach ($this->exchanges as $exchange) {
            if ($exchange->step === $step) {
                return $exchange;
            }
        }

        return null;
    }

    /**
     * The learner's lines that stand on a frame, in the order of the visit — one for a frame said
     * once, two for a frame said in two exchanges.
     *
     * @return list<array{exchange: Exchange, message: Message}>
     */
    public function linesOf(string $phraseId): array
    {
        $out = [];
        foreach ($this->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                if ($message->isLearner() && $message->phraseId === $phraseId) {
                    $out[] = ['exchange' => $exchange, 'message' => $message];
                }
            }
        }

        return $out;
    }

    /** @param list<Exchange> $exchanges */
    public function withExchanges(array $exchanges): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative,
            $this->learnerRoleTarget, $this->learnerRoleNative, $this->roleGender,
            $exchanges, $this->phrases, $this->listening, $this->vocabulary,
        );
    }

    /** @param list<Phrase> $phrases */
    public function withPhrases(array $phrases): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative,
            $this->learnerRoleTarget, $this->learnerRoleNative, $this->roleGender,
            $this->exchanges, $phrases, $this->listening, $this->vocabulary,
        );
    }

    /** @param list<ListeningQuestion> $listening */
    public function withListening(array $listening): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative,
            $this->learnerRoleTarget, $this->learnerRoleNative, $this->roleGender,
            $this->exchanges, $this->phrases, $listening, $this->vocabulary,
        );
    }

    /**
     * The shape of the prompt's STRICT OUTPUT SCHEMA, keys in its order — what `plan_scenes.lesson_json` holds.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'topic' => [
                'title_target' => $this->titleTarget,
                'title_native' => $this->titleNative,
                'description_target' => $this->descriptionTarget,
                'description_native' => $this->descriptionNative,
            ],
            'learner_role' => [
                'role_target' => $this->learnerRoleTarget,
                'role_native' => $this->learnerRoleNative,
            ],
            'role_gender' => $this->roleGender?->value,
            'dialogue' => array_map(static fn (Exchange $e): array => $e->toArray(), $this->exchanges),
            'phrases' => array_map(static fn (Phrase $p): array => $p->toArray(), $this->phrases),
            'listening' => [
                'questions' => array_map(static fn (ListeningQuestion $q): array => $q->toArray(), $this->listening),
            ],
            'vocabulary' => array_map(static fn (VocabularyItem $v): array => $v->toArray(), $this->vocabulary),
        ];
    }
}
