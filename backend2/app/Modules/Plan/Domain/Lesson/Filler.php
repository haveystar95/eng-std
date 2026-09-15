<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * ONE VALUE OF A FRAME'S SLOT (`lesson_day.v4.5`, FILLERS): the words that go into `___`, their
 * translation and reading, and whether the dialogue says the frame with this value. A frame said in
 * two exchanges has two fillers marked — one per exchange.
 */
final readonly class Filler
{
    public function __construct(
        public string $target,
        public string $native,
        public string $pronunciationNative,
        public bool $inDialogue,
    ) {}

    public function withInDialogue(bool $inDialogue): self
    {
        return new self($this->target, $this->native, $this->pronunciationNative, $inDialogue);
    }

    /** @return array{target: string, native: string, pronunciation_native: string, in_dialogue: bool} */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'native' => $this->native,
            'pronunciation_native' => $this->pronunciationNative,
            'in_dialogue' => $this->inDialogue,
        ];
    }
}
