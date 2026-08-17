<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One page of a learner's pairs, with the phase split that belongs ABOVE it.
 *
 * The counters travel with the page rather than in a second endpoint because the screen polls: two
 * requests four seconds apart would routinely show a table and a header describing different
 * moments, and the eye reads that as a bug in the ladder rather than in the panel.
 */
final readonly class AdminLadderProgressView
{
    /** @param Page<AdminLadderPair> $page */
    public function __construct(
        public Page $page,
        public AdminLadderCounts $counts,
    ) {}
}
