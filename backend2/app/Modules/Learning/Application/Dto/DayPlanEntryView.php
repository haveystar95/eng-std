<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * One term the day-plan simulator says the user would meet on the target date, and how: which
 * trainer the ExerciseSelector would hand it (the real scheduler answer, not a SQL guess), plus the
 * signals that drove that choice (state, reps, clozeable). Read-only — producing this writes nothing.
 */
final readonly class DayPlanEntryView
{
    public function __construct(
        public string $termId,
        public string $text,            // the target-language answer
        public ?string $translation,    // the prompt (user's language)
        public string $type,            // word | phrase | idiom | phrasal_verb
        public string $state,           // learning state (new | learning | review | relearning | known)
        public int $reps,
        public int $intervalDays,
        public ?string $dueAt,          // ISO-8601, null for a brand-new term
        public string $exerciseMode,    // what ExerciseSelector::select would pick
        public bool $clozeable,         // example exists and contains the answer
        public bool $isNew,             // introduced under today's quota (no progress row yet)
    ) {}
}
