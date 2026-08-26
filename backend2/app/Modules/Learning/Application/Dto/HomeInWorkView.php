<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * «В работе — N слов · K ждут очереди · при T в день новым до очереди ~D дней».
 *
 * The размер ящика, which the product research named as the main unanswered question of the old
 * home screen: how much have I taken on, and when does the app get to it. Every number is the
 * POOL's — `enrolled_at IS NOT NULL` — because the pool is the queue and a collection is a
 * catalogue.
 */
final readonly class HomeInWorkView
{
    public function __construct(
        /** Pool size: every word the learner has taken into study. */
        public int $total,
        /** Of those, the ones never shown — rung 0, waiting for a day's new-term quota. */
        public int $waiting,
        /** The daily new-term quota, as the session builder applies it. */
        public int $perDay,
        /** How much of today's quota is left (same figure as `/stats.new_remaining`, same source). */
        public int $newRemaining,
        /**
         * Days until the last waiting word gets its first card: today's remaining quota is spent
         * first, then whole days of `perDay`. Null when nothing is waiting, and null when `perDay`
         * is 0 — «новых не берём» has no answer in days, and printing ∞ is not one either.
         */
        public ?int $daysUntilQueue,
    ) {}
}
