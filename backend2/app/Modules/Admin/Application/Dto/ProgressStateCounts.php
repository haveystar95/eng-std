<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * Raw tally of a user's progress rows by their stored `state` column. This is a literal state
 * breakdown, NOT the "усвоено"/mastered definition — mastered is asked of the Learning module
 * (Mastery), the single source of that truth, and reported separately on the detail view.
 */
final readonly class ProgressStateCounts
{
    public function __construct(
        public int $total,
        public int $learning,
        public int $review,
        public int $relearning,
        public int $known,
    ) {}
}
