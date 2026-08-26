<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * «Сессия на сегодня: N слов · ~M минут», and what it is made of.
 *
 * `total` is `repeat + new` — THE POOL, which is the queue. `triage` is counted beside it and is
 * deliberately NOT in the total: those terms are CATALOGUE, words the learner owns and has not
 * chosen to study, and adding a set would otherwise add its whole size to «сегодня». It is offered
 * once the repeats are done, priced by `triageMinutes`.
 *
 * A count of 0 is a real answer here (the client draws no line for it) — the whole card is absent
 * only when {@see HomePlanView::$state} says so.
 */
final readonly class HomeSessionView
{
    public function __construct(
        public int $repeat,
        public int $new,
        public int $triage,
        public int $total,
        /** null when there is nothing to do — «≈ 0 минут» is not a thing the screen says. */
        public ?int $estimatedMinutes,
        /** The per-card figure the estimate was built from; see {@see \App\Modules\Learning\Application\Port\HomePlanReader::averageCardSeconds()}. */
        public int $avgSecondsPerCard,
        /**
         * What the swipe pass would cost, in minutes — null when there is nothing to sort. Priced at
         * the learner's own SWIPE rate, which is a different act and measures like one (≈3 s against
         * ≈8–11 s), so a hundred-word pass reads as the five minutes it is.
         */
        public ?int $triageMinutes,
        /** Where the swipe pass leads; null when `triage` is 0. */
        public ?string $triageCollectionId = null,
        public ?string $triageCollectionTitle = null,
    ) {}
}
