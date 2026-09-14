<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/** The slot of a frame: what kind of value goes into `___` (in the learner's language) and its 2–3 values. */
final readonly class Slot
{
    /** @param list<Filler> $fillers */
    public function __construct(
        public string $hintNative,
        public array $fillers,
    ) {}

    /**
     * The filler written as `$target` — verbatim first, then ignoring case (a filler copied at the
     * start of a line keeps its capital letter).
     */
    public function filler(string $target): ?Filler
    {
        foreach ($this->fillers as $filler) {
            if ($filler->target === $target) {
                return $filler;
            }
        }
        foreach ($this->fillers as $filler) {
            if (mb_strtolower(trim($filler->target)) === mb_strtolower(trim($target))) {
                return $filler;
            }
        }

        return null;
    }

    /** @return array{hint_native: string, fillers: list<array{target: string, native: string, pronunciation_native: string, in_dialogue: bool}>} */
    public function toArray(): array
    {
        return [
            'hint_native' => $this->hintNative,
            'fillers' => array_map(static fn (Filler $f): array => $f->toArray(), $this->fillers),
        ];
    }
}
