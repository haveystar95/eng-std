<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * «Сессия на сегодня: N слов · ~M минут», and what it is made of.
 *
 * `repeat` and `new` are the REAL planner answer — the same {@see \App\Modules\Learning\Application\Query\GetDueTermsHandler}
 * call the session builder makes, under the same session size and the same remaining new-term quota
 * — so the card can never promise a session the server would come back empty from. `triage` is the
 * swipe pass beside it: terms of the learner's collections that have neither progress nor a triage
 * verdict. It is not part of a study session and never has been; it is part of the DAY, which is
 * what this card is about.
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
        /** Where the swipe pass leads; null when `triage` is 0. */
        public ?string $triageCollectionId = null,
        public ?string $triageCollectionTitle = null,
    ) {}
}
