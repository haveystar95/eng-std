<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * Derived learning progress for one collection (never stored — computed from the (user, term)
 * progress of its terms). `mastered` is the single «усвоено» definition; `confirmed` and
 * `familiar` are a BREAKDOWN of it (proven by exercises vs self-marked known), not a second
 * definition. The four segments new + inProgress + confirmed + familiar sum to total.
 */
final readonly class CollectionProgressView
{
    public function __construct(
        public string $collectionId,
        public int $total,        // terms in the collection
        public int $newCount,     // not started (no row, or returned to new)
        public int $due,          // studied terms due now
        public int $mastered,     // confirmed + familiar (the one «усвоено»)
        public int $confirmed,    // proven by exercises: review & interval >= 21
        public int $familiar,     // marked known (self-assessed, awaiting proof)
        public int $inProgress,   // started but not yet mastered
    ) {}
}
