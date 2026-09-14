<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * A word or a chunk of the day, and where the lesson says it (`used_in`): frame ids (`p3` — the frame
 * or one of its fillers) and partner lines (`A3` — the partner's message of exchange 3).
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
