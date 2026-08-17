<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * The blast radius of touching one term: how many collections hold it and how many learners have
 * progress on it. Shown in the confirmation dialog, because "delete this term" reads very
 * differently when it means "and 3 people lose their progress on it".
 */
final readonly class TermImpact
{
    public function __construct(
        public string $termId,
        public string $text,
        public int $collectionsCount,
        public int $usersWithProgress,
        public int $reviewsCount,
    ) {}
}
