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
     * learner actually met the word»). A reader that asks «when was this word LAST shown» — a
     * re-introduction after a long pause — got a date months old and dropped every fresh intro
     * on the way in (measured on the owner's device, 02.09: six uploaded exposures ignored).
     *
     * So: shown is RECORDED. Only forward — a showing older than the one on file (a replayed
     * offline batch, a device clock behind) leaves the row alone, which keeps the write idempotent
     * in the direction idempotency was ever about.
     */
    public function record(TermExposure $exposure): bool;
}
