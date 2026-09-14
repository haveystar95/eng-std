<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * ONE QUESTION ABOUT THE WHOLE VISIT, HEARD ONCE WITHOUT TEXT (`lesson_day.v4.4`, LISTENING): what
 * was agreed, recommended or said — in the learner's language only, three options, one right. Not a
 * field of an exchange: the listening belongs to the lesson.
 */
final readonly class ListeningQuestion
{
    /** @param list<string> $optionsNative */
    public function __construct(
        public string $textNative,
        public array $optionsNative,
        public int $correctOptionIndex,
        public string $explanationNative,
    ) {}

    public function correctOption(): ?string
    {
        return $this->optionsNative[$this->correctOptionIndex] ?? null;
    }

    /** @param list<string> $optionsNative */
    public function withOptions(array $optionsNative, int $correctOptionIndex): self
    {
        return new self($this->textNative, $optionsNative, $correctOptionIndex, $this->explanationNative);
    }

    /** @return array{text_native: string, options_native: list<string>, correct_option_index: int, explanation_native: string} */
    public function toArray(): array
    {
        return [
            'text_native' => $this->textNative,
            'options_native' => $this->optionsNative,
            'correct_option_index' => $this->correctOptionIndex,
            'explanation_native' => $this->explanationNative,
        ];
    }
}
