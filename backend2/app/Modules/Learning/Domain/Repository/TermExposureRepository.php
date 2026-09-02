<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\TermExposure;

/**
 * The intro log, keyed by the PAIR `(user_id, term_id)` — one row per word a learner has met.
 *
 * Unlike `reviews` and `term_triages` this is not an append-only EVENT log, and the difference is
 * the whole of {@see record()}: the row is the standing fact «this pair has been introduced», and
 * the last showing is when it was last stated.
 */
interface TermExposureRepository
{
    /**
     * THE PAIR WAS SHOWN. Returns true only when this is the FIRST time — the caller steps the
     * ordinary ladder onto rung 1 on that answer and must not step it twice.
     *
     * ## Why the row MOVES, and what that fixed
     *
     * It used to be an ignored insert that kept the first `shown_at` for ever («the moment the
     * learner actually met the word»). That reading was written before a plan's ladder was scoped
     * to the plan: a plan counts only what happened AFTER the card joined it
     * ({@see \App\Modules\Learning\Application\Port\PlanStandingsReader::introducedAmong()}), so a
     * word met in an earlier plan carried an exposure dated before this plan existed, every intro
     * this plan showed was dropped on the way in, and the step never closed.
     *
     * Measured, on the owner's live day 1 (02.09, plan `01M1HZF4…`): the card «available» joined
     * the day at 21:14:05 with an exposure of 2026-09-01 11:19 behind it. The client uploaded a
     * fresh exposure for it SIX times — 21:50, 21:55, 21:57, 21:59, 22:01, 22:03 — the server
     * ignored all six, and the learner was introduced to the same word in six sittings running
     * while the day it belongs to could not close.
     *
     * So: shown is RECORDED. Only forward — a showing older than the one on file (a replayed
     * offline batch, a device clock behind) leaves the row alone, which keeps the write idempotent
     * in the direction idempotency was ever about.
     */
    public function record(TermExposure $exposure): bool;
}
