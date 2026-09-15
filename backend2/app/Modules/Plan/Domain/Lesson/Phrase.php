<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * A PHRASE OF THE DAY IS A FRAME (`lesson_day.v4.5`, FRAMES): a reusable sentence pattern with one
 * slot `___` (or none), its rendering in the learner's language and its reading — both with `___`
 * kept — and the slot's hint and values. The learner's dialogue lines are the frame said with one of
 * its fillers; the server puts them together ({@see \App\Modules\Plan\Domain\Service\FrameText}).
 */
final readonly class Phrase
{
    public function __construct(
        public string $id,
        public ExchangeKind $kind,
        public string $frameTarget,
        public string $frameNative,
        public string $pronunciationNative,
        public ?Slot $slot,
    ) {}

    /** The filler written as `$target`, when the frame has a slot and the value is one of its fillers. */
    public function filler(?string $target): ?Filler
    {
        return $target === null ? null : $this->slot?->filler($target);
    }

    /** @return list<Filler> */
    public function fillers(): array
    {
        return $this->slot->fillers ?? [];
    }

    /**
     * The same frame with `in_dialogue` on exactly the fillers at `$said` (their places in the slot) — the marks the
     * server serves, set by what the dialogue says, not by the model's marks.
     *
     * @param  list<int>  $said
     */
    public function withFillersSaid(array $said): self
    {
        if ($this->slot === null) {
            return $this;
        }
        $fillers = array_map(
            static fn (Filler $f, int $i): Filler => $f->withInDialogue(in_array($i, $said, true)),
            $this->slot->fillers,
            array_keys($this->slot->fillers),
        );

        return new self($this->id, $this->kind, $this->frameTarget, $this->frameNative, $this->pronunciationNative, new Slot($this->slot->hintNative, $fillers));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'frame_target' => $this->frameTarget,
            'frame_native' => $this->frameNative,
            'pronunciation_native' => $this->pronunciationNative,
            'slot' => $this->slot?->toArray(),
        ];
    }
}
