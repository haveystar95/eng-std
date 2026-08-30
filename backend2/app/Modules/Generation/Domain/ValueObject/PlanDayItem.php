<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * One card of a plan day as the model wrote it — before anything has decided whether it is any
 * good.
 *
 * `type` and `isLine` are two answers to two different questions and the entries where they
 * DISAGREE are the ones this type exists for: «code review» is `type: phrase` (multi-word by
 * grammar) and `isLine: false` (a substitution by function). Collapsing them into one field forced
 * a lie about one of the two, which is what v0 did.
 */
final readonly class PlanDayItem
{
    public function __construct(
        public string $text,
        /** word | phrase | idiom | phrasal_verb — what it IS, lexically. */
        public string $type,
        /** true for a spoken turn in this day's conversation — what it DOES. */
        public bool $isLine,
        public string $translation,
        public ?string $transliteration,
        public string $description,
        public string $example,
        public string $exampleTranslation,
        /** 1-based checkpoint this reply closes; always null on a substitution. */
        public ?int $coversCheckpoint,
    ) {}
}
