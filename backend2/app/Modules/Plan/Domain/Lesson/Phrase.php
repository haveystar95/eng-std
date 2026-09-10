<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/** A learner phrase: the core of one of B's messages, made to stand alone. */
final readonly class Phrase
{
    public function __construct(
        public string $id,
        public string $textTarget,
        public string $textNative,
        public string $pronunciationNative,
    ) {}

    public function withoutPronunciation(): self
    {
        return new self($this->id, $this->textTarget, $this->textNative, '');
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text_target' => $this->textTarget,
            'text_native' => $this->textNative,
            'pronunciation_native' => $this->pronunciationNative,
        ];
    }
}
