<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\PlanStageFact;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeZone;

/**
 * The two reads the plan's stage ladder needs, and nothing else.
 *
 * A stage is a projection over the append-only review log ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}),
 * so what this port fetches is that log, reduced to (which trainer, did it work, on which of the
 * learner's own days) — plus the one step that writes no review at all, the intro, which is a
 * `term_exposures` row.
 *
 * The timezone is an argument rather than something the implementation looks up, because «после
 * ночи» is a statement about the learner's calendar and the caller is the one that already knows
 * whose calendar it is.
 */
interface PlanStandingsReader
{
    /**
     * Every non-practice answer these terms have collected, oldest first, per term.
     *
     * Practice answers are excluded on purpose and for the reason practice is excluded everywhere:
     * free training never schedules and never advances anything, so it must not close a stage step
     * either — otherwise a learner could walk a word to «готово» without the plan ever asking it a
     * question.
     *
     * Answers from BEFORE the term joined the plan are excluded too, and for a reason of the same
     * shape: they were given somewhere else. See {@see $since}.
     *
     * @param  list<string>  $termIds
     * @param  array<string, \DateTimeImmutable>  $since  term id => the moment this card joined the
     *         plan. Anything answered before it belongs to another plan or to the learner's
     *         notebook and is no evidence about THIS card. A term absent from the map has no
     *         cutoff — the whole log counts, which is what every caller outside a plan wants.
     * @return array<string, list<PlanStageFact>>  term id => facts
     */
    public function factsFor(UserId $user, array $termIds, DateTimeZone $tz, array $since = []): array;

    /**
     * Which of these terms this learner has already been SHOWN — the intro step's evidence.
     *
     * @param  list<string>  $termIds
     * @param  array<string, \DateTimeImmutable>  $since  as in {@see factsFor()}: an exposure older
     *         than the card's arrival in this plan is a meeting that happened elsewhere, and the
     *         plan's own intro is still owed.
     * @return array<string, bool>  term id => true (absent means «never shown»)
     */
    public function introducedAmong(UserId $user, array $termIds, array $since = []): array;
}
