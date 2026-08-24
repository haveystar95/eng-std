<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Reports the two ways a card can come out wrong without anything failing.
 *
 * Both exist so the outcome is never silent. A learner whose toggles leave a term with nowhere to
 * go still gets a playable card, and a term with nothing to offer beside its answer now gets no
 * card at all — neither produces an error, and neither produces a complaint the owner can act on.
 * Exactly the shape of a bug that lives for months. This is the trace.
 */
interface ModeFallbackReporter
{
    /** @param list<string> $enabledModes the toggles that were on when nothing fitted */
    public function noApplicableMode(UserId $userId, TermId $termId, array $enabledModes): void;

    /**
     * No card at all, because the term's LANGUAGE carries no trainer (DECISIONS пп. 130, 136).
     *
     * Its own method and not a third argument on the one above, for the reason
     * {@see \App\Modules\Learning\Domain\Service\ModePassport::closedByLanguage()} gives: «закрыт
     * языком» and «закрыт матрицей» look identical from the outside and have opposite cures. A zh
     * word in the pool is a rule broken upstream (reference collections do not enrol); a
     * `pick_correct` missing on a Polish card is the design working.
     */
    public function closedByLanguage(UserId $userId, TermId $termId, string $lang, string $reason): void;

    /**
     * A choice card was REFUSED because the term could not furnish a second option (QA-15).
     *
     * The learner sees one card fewer, which is right — one option is not a question — but the
     * cause is the term's data, not their toggles, and nobody would ever guess at it from a session
     * that is simply shorter than expected.
     */
    public function tooFewOptions(UserId $userId, TermId $termId, string $mode, int $options): void;
}
