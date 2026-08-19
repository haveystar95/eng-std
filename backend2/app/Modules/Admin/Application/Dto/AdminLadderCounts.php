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
        /**
         * Pairs OUTSIDE the pool — a row exists, but the trainer will never deal it: a «знаю»
         * self-assessment awaiting its check, or a word the learner paused.
         *
         * It cuts ACROSS the four phase buckets rather than being a fifth one, so it is not part of
         * their sum. Note also what it cannot see: a word sitting in a collection the learner has
         * never triaged has no row at all, so «в каталоге, не в пуле» in the fullest sense is a
         * different query over `collection_items` and a screen of its own.
         */
        public int $outOfPool = 0,
    ) {}
}
