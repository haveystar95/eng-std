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
     * A choice card was REFUSED because the term could not furnish a second option (QA-15).
     *
     * The learner sees one card fewer, which is right — one option is not a question — but the
     * cause is the term's data, not their toggles, and nobody would ever guess at it from a session
     * that is simply shorter than expected.
     */
    public function tooFewOptions(UserId $userId, TermId $termId, string $mode, int $options): void;
}
