<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * THE CHECK OF ONE EXCHANGE (`lesson_day.v4.4`, CHECK PER EXCHANGE): did the learner understand what
 * the partner said in THIS exchange — three options in both languages, one right, and why.
 */
final readonly class ExchangeCheck
{
    /** @param list<CheckOption> $options */
    public function __construct(
        public string $textTarget,
        public string $textNative,
        public array $options,
        public int $correctOptionIndex,
        public string $explanationNative,
    ) {}

    public function correctOption(): ?CheckOption
    {
        return $this->options[$this->correctOptionIndex] ?? null;
    }

    /** @param list<CheckOption> $options */
    public function withOptions(array $options, int $correctOptionIndex): self
    {
        return new self($this->textTarget, $this->textNative, $options, $correctOptionIndex, $this->explanationNative);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'text_target' => $this->textTarget,
            'text_native' => $this->textNative,
            'options' => array_map(static fn (CheckOption $o): array => $o->toArray(), $this->options),
            'correct_option_index' => $this->correctOptionIndex,
            'explanation_native' => $this->explanationNative,
        ];
    }
}
