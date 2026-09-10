<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

final readonly class QuestionOption
{
    public function __construct(
        public string $textTarget,
        public string $textNative,
    ) {}

    /** @return array{text_target: string, text_native: string} */
    public function toArray(): array
    {
        return ['text_target' => $this->textTarget, 'text_native' => $this->textNative];
    }
}
