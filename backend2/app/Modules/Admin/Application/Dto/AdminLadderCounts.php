<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * The phase split above the progress table.
 *
 * Four buckets, and they do not overlap: `known` is a STATE (a triage self-assessment), the other
 * three are the `acquisition` dimension. A pair that is `known` also carries some acquisition value,
 * so it is counted as known and nowhere else — otherwise the four numbers would sum to more than
 * the pairs that exist and the panel would be lying about a total.
 */
final readonly class AdminLadderCounts
{
    public function __construct(
        public int $total,
        public int $new,
        public int $learning,
        public int $graduated,
        public int $known,
        /** Scheduled and ripe right now. Ladder pairs have no due date at all and are never here. */
        public int $due,
    ) {}
}
