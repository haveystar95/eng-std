<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/** A word or a chunk of the day. */
final readonly class VocabularyItem
{
    public const KIND_WORD = 'word';

    public const KIND_CHUNK = 'chunk';

    public function __construct(
        public string $id,
        public string $termTarget,
        public string $translationNative,
        public string $pronunciationNative,
        public string $definitionTarget,
        public string $kind,
        public ?string $imagePrompt,
    ) {}

    public function withoutPronunciation(): self
    {
        return new self(
            $this->id, $this->termTarget, $this->translationNative, '', $this->definitionTarget, $this->kind, $this->imagePrompt,
        );
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
        ];
    }
}
