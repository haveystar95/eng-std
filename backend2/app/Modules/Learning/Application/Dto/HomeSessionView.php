<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * «Сессия на сегодня: N слов · ~K карточек · ~M минут», and what it is made of.
 *
 * `total` is `repeat + new` — THE POOL, which is the queue. `triage` is counted beside it and is
 * deliberately NOT in the total: those terms are CATALOGUE, words the learner owns and has not
 * chosen to study, and adding a set would otherwise add its whole size to «сегодня». It is offered
 * once the repeats are done, priced by `triageMinutes`.
 *
 * WORDS AND CARDS ARE BOTH HERE because the day is honestly both, and the card promised only the
 * first while the session counted the second. They are not proportional: a word met today brings
 * its intro and both recognitions in one sitting, a graduated one brings a single card, so twenty
 * words is anything from twenty to sixty. `total` is the work, `cards` is the length.
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
        /**
         * How many CARDS those `total` words will be dealt — the number the session's own counter
         * will count up to, from the same rungs the session builder reads.
         */
        public int $cards,
        /**
         * null when there is nothing to do — «≈ 0 минут» is not a thing the screen says.
         *
         * Priced in CARDS × seconds-per-card. It used to be words × seconds-per-card, which is two
         * different units multiplied together: a first day of twenty new words was sold as three
         * minutes and dealt sixty cards.
         */
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
        /**
         * When the day's whole outstanding work is ONE word still climbing its chain: how many cards
         * that chain holds in total. Null otherwise — including for a lone graduated repeat, whose
         * «chain» is one card and whose progress is therefore nothing to report.
         *
         * With [cards] beside it the screen can say «карточка 2 из 3»: the position is
         * `chainTotal - cards + 1`, because [cards] is what is LEFT. A learner finishing one word
         * wants to know how much of it is left, and «1 слово» alone says the same thing at the start
         * of the chain as at its end.
         */
        public ?int $chainTotal = null,
    ) {}
}
