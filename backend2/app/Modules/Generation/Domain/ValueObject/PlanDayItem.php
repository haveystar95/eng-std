<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * One card of a plan day as the model wrote it — before anything has decided whether it is any
 * good.
 *
 * ## Three fields answer three different questions, and the entries where they disagree are why
 *
 * `type` — what the expression IS, lexically: word, phrase, idiom, phrasal_verb.
 * `kind` — what it DOES in this day: a line the learner says, a word that goes in a slot, or a
 *          connector that also goes in a slot. This is what the stage checklist reads.
 * `isLine` — the older, coarser form of `kind`, kept because it is written for every term the plan
 *          touches and is what `terms.is_line` has meant since PLAN-1a.
 *
 * «payment module» is `type: phrase`, `kind: word`, `isLine: false` — multi-word by grammar, a
 * substitution by function. «deal with» is `type: phrasal_verb`, `kind: chunk`. Collapsing any two
 * of the three forces a lie about one of them, which is what v0 did with a single `kind` field.
 */
final readonly class PlanDayItem
{
    public const KIND_LINE = 'line';
    public const KIND_WORD = 'word';
    public const KIND_CHUNK = 'chunk';

    public const SPEAKER_LEARNER = 'learner';
    public const SPEAKER_ROLE = 'role';

    public function __construct(
        public string $text,
        /** word | phrase | idiom | phrasal_verb — what it IS, lexically. */
        public string $type,
        /** line | word | chunk — what it DOES in this day. */
        public string $kind,
        /** true for a spoken turn in this day's conversation. `kind === 'line'`, said the old way. */
        public bool $isLine,
        public string $translation,
        public ?string $transliteration,
        public string $description,
        public string $example,
        public string $exampleTranslation,
        /**
         * The line with its slot written as `___`, or '' for a formula the learner says whole.
         * Empty on everything that is not a line.
         */
        public string $frame = '',
        /** learner | role on a line, null on a substitution. */
        public ?string $speaker = null,
        /** One English sentence describing the picture. Never empty on a v0.2 day. */
        public string $imageApiPrompt = '',
        /** 1-based checkpoint this line closes; always null on a substitution. */
        public ?int $coversCheckpoint = null,
    ) {}
}
