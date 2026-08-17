<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * Everything the progress table stores about one (user, term) pair, exactly as it is stored.
 *
 * Deliberately WITHOUT the ladder rung. The rung is derived, not stored, and its derivation belongs
 * to the Learning module ({@see \App\Modules\Learning\Application\Service\LadderStepResolver}) —
 * which this module's Infrastructure may not reach. So the reader returns the facts and the query
 * handler pairs them with the rung ({@see AdminLadderPair}); the boundary holds and the ladder keeps
 * exactly one definition.
 */
final readonly class AdminLadderPairFacts
{
    /** @param list<CollectionRefRow> $collections the user's own collections this term came from */
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        public array $collections,
        public string $state,
        public string $acquisition,
        public int $learningStep,
        public int $reps,
        public int $lapses,
        public int $intervalDays,
        public ?string $dueAt,
        public ?string $lastReviewedAt,
        /** When the intro card was shown, or null if the word has never been introduced. */
        public ?string $exposedAt,
        public ?AdminLadderReview $lastReview,
    ) {}

    /** A `known` pair is a triage self-assessment and stands outside the ladder entirely. */
    public function isKnown(): bool
    {
        return $this->state === 'known';
    }
}
