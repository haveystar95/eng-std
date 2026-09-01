<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

/**
 * WHILE A PLAN IS RUNNING, ITS WORDS LIVE IN THE PLAN — one rule, written once, applied by every
 * read that describes the ORDINARY day.
 *
 * A plan enrols its words into the pool and stamps `enrollment_sources` with `plan:<ULID>`
 * ({@see \App\Modules\Learning\Domain\ValueObject\EnrollmentSources}). Without this predicate those
 * words are also dealt by the ordinary session, counted in «Сегодня · 24 слова», and listed under
 * «Завтра выпадет N» — so the learner meets each of them twice a day under two different headings,
 * and the two screens disagree about how much work the day holds. Кадр 08 puts the plan card ABOVE
 * the «сегодня» plate precisely because they are two different piles.
 *
 * What happens when the plan ENDS used to be the other half of the same rule and needed no code
 * here: the release removed the `plan:` source, this predicate stopped matching, and the words
 * rejoined the ordinary rotation. That is over (owner, 01.09). An ended plan is an archive and takes
 * its words out of the pool with it ({@see \App\Modules\Learning\Application\Port\PlanTermArchiver}),
 * so a pair leaves this predicate's reach by leaving the pool, not by rejoining the queue. What this
 * predicate still exists for is unchanged: while the plan RUNS, its words are dealt by it alone.
 *
 * ## Why it is SQL and not a list of plan ids
 *
 * The alternative was to read the holding plan ids in PHP and pass them into every reader. That is
 * a parameter on ten methods across two ports, all of which would default to «no exclusion» — and a
 * default that silently means «show plan words too» is a default some future call site will take by
 * accident. Expressed as a correlated NOT EXISTS, the rule cannot be forgotten by a caller, because
 * callers do not carry it.
 *
 * `jsonb_exists(col, 'plan:' || id)` is the function spelling of the jsonb `?` operator. The
 * operator itself cannot be used through a query builder at all: `?` is the parameter placeholder.
 */
final class PlanHeldTerms
{
    /**
     * A `whereRaw` fragment: true for a progress row NO running plan is standing on.
     *
     * The holding statuses are `active` and `paused` — {@see
     * \App\Modules\Learning\Domain\ValueObject\PlanStatus::holdsTerms()}, and a pause means «я
     * вернусь», so a paused plan keeps its words out of the ordinary day too.
     *
     * A CONSTANT and not a function of the table name: both readers query `user_term_progress`
     * unaliased, and PHPStan wants `whereRaw` to receive a literal string — which is the right
     * pressure to apply to a fragment of hand-written SQL.
     */
    public const NOT_HELD = <<<'SQL'
        NOT EXISTS (
            SELECT 1 FROM learning_plans lp
            WHERE lp.user_id = user_term_progress.user_id
              AND lp.status IN ('active', 'paused')
              AND jsonb_exists(user_term_progress.enrollment_sources, 'plan:' || lp.id)
        )
        SQL;
}
