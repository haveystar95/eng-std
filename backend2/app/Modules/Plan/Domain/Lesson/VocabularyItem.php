<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * A word or a chunk of the day, and where it is said (`used_in`): frame ids (`p3` — the frame or one of its fillers) and
 * partner lines — in the skeleton a partner line's own id (`a4`), in the lesson the partner's message of the exchange that
 * carries it (`A3`, {@see LessonAssembler}).
 */
final readonly class VocabularyItem
{
    public const KIND_WORD = 'word';

    public const KIND_CHUNK = 'chunk';

    /** @param list<string> $usedIn */
    public function __construct(
        public string $id,
        public string $termTarget,
        public string $translationNative,
        public string $pronunciationNative,
        public string $definitionTarget,
        public string $kind,
        public ?string $imagePrompt,
        public array $usedIn = [],
    ) {}

    /** The same word under another id — a repaired word keeps the id of the one it replaces. */
    public function withId(string $id): self
    {
        return new self($id, $this->termTarget, $this->translationNative, $this->pronunciationNative, $this->definitionTarget, $this->kind, $this->imagePrompt, $this->usedIn);
    }

    /** @param list<string> $usedIn */
    public function withUsedIn(array $usedIn): self
    {
        return new self($this->id, $this->termTarget, $this->translationNative, $this->pronunciationNative, $this->definitionTarget, $this->kind, $this->imagePrompt, $usedIn);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'term_target' => $this->termTarget,
            'translation_native' => $this->translationNative,
            'pronunciation_native' => $this->pronunciationNative,
            'definition_target' => $this->definitionTarget,
            'kind' => $this->kind,
            'image_prompt' => $this->imagePrompt,
            'used_in' => $this->usedIn,
        ];
    }
}
