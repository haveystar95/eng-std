<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/** The comprehension question of one exchange: what A said, tested with three options. */
final readonly class Question
{
    /** @param list<QuestionOption> $options */
    public function __construct(
        public string $textTarget,
        public string $textNative,
        public array $options,
        public int $correctOptionIndex,
        public string $explanationNative,
    ) {}

    public function correctOption(): ?QuestionOption
    {
        return $this->options[$this->correctOptionIndex] ?? null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'text_target' => $this->textTarget,
            'text_native' => $this->textNative,
            'options' => array_map(static fn (QuestionOption $o): array => $o->toArray(), $this->options),
            'correct_option_index' => $this->correctOptionIndex,
            'explanation_native' => $this->explanationNative,
        ];
    }
}
