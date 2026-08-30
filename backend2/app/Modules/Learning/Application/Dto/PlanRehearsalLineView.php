<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** One sentence to say out loud before the event, with the question it answers. */
final readonly class PlanRehearsalLineView
{
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        /**
         * What the person on the other side says — «How long has it been like this?».
         *
         * Taken from the day's role brief (`role.opening_lines`), which is the only place in the
         * plan that holds actual utterances by the interlocutor. Null when the day had no role: a
         * day of filling in forms has nobody to answer, and inventing a prompt for it would be
         * putting words in a mouth that is not there.
         */
        public ?string $cue,
        public ?string $role,
        public int $dayIndex,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->termId,
            'text' => $this->text,
            'translation' => $this->translation,
            'cue' => $this->cue,
            'role' => $this->role,
            'day_index' => $this->dayIndex,
        ];
    }
}
