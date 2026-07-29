<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** Derived learning progress for one collection (never stored — computed from reviews). */
final readonly class CollectionProgressView
{
    public function __construct(
        public string $collectionId,
        public int $total,
        public int $learned,   // terms in `review` state
        public int $mastered,  // `review` and interval >= 21 days
        public int $due,       // studied terms due now
    ) {}
}
