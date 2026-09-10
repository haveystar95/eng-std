<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * The lesson of one scene as the model wrote it — and as the checks may have corrected it.
 *
 * Immutable: every correction returns a new lesson, so the one stored beside the scene is the one
 * every reader assembles the day from. Parsed from the model's JSON by {@see LessonParser}; a
 * lesson that cannot be parsed never exists as a value.
 */
final readonly class Lesson
{
    /**
     * @param  list<Exchange>  $exchanges
     * @param  list<Phrase>  $phrases
     * @param  list<VocabularyItem>  $vocabulary
     */
    public function __construct(
        public string $titleTarget,
        public string $titleNative,
        public string $descriptionTarget,
        public string $descriptionNative,
        public string $learnerRoleTarget,
        public string $learnerRoleNative,
        public array $exchanges,
        public array $phrases,
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

    /** @return list<VocabularyItem> */
    public function words(): array
    {
        return array_values(array_filter(
            $this->vocabulary,
            static fn (VocabularyItem $v): bool => $v->kind === VocabularyItem::KIND_WORD,
        ));
    }

    /** @param list<Exchange> $exchanges */
    public function withExchanges(array $exchanges): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative,
            $this->learnerRoleTarget, $this->learnerRoleNative, $exchanges, $this->phrases, $this->vocabulary,
        );
    }

    /** @param list<Phrase> $phrases */
    public function withPhrases(array $phrases): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative,
            $this->learnerRoleTarget, $this->learnerRoleNative, $this->exchanges, $phrases, $this->vocabulary,
        );
    }

    /** @param list<VocabularyItem> $vocabulary */
    public function withVocabulary(array $vocabulary): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative,
            $this->learnerRoleTarget, $this->learnerRoleNative, $this->exchanges, $this->phrases, $vocabulary,
        );
    }

    /**
     * The same shape the prompt's schema describes — what is stored in `plan_scenes.lesson_json`.
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
            'dialogue' => array_map(static fn (Exchange $e): array => $e->toArray(), $this->exchanges),
            'phrases' => array_map(static fn (Phrase $p): array => $p->toArray(), $this->phrases),
            'vocabulary' => array_map(static fn (VocabularyItem $v): array => $v->toArray(), $this->vocabulary),
        ];
    }
}
