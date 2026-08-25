<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermReadingTargetView;

/**
 * Returns the term IF it still has no reading hint in this SUPPORT language, else null.
 *
 * The idempotency gate of the reading path, and the same shape as {@see EnrichableTermReader} for
 * the same reason: a term is globally deduplicated, so the word a learner is saving today may have
 * been given its hint by a collection generated a month ago — and the only way to not re-pay for
 * that is to ask before calling the model, not after ({@see \App\Modules\Vocabulary\Application\Port\TermTransliterationWriter}
 * would refuse the second write, but the call would already be bought).
 *
 * Keyed by the support language and not by the term's own: one term legitimately carries one hint
 * per pair — «cómo estás» reads one way for a Russian learner and another for a Ukrainian one.
 */
interface TermReadingTargetReader
{
    public function find(TermId $termId, string $supportLang): ?TermReadingTargetView;
}
