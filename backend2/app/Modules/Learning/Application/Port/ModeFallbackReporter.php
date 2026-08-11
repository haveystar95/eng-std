<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Reports that a card had to fall back to multiple_choice because no enabled mode fitted the term.
 *
 * It exists so the fallback is never silent. A learner whose toggles leave a term with nowhere to
 * go still gets a playable card, which means the misconfiguration produces no error, no empty
 * screen and no complaint — exactly the shape of a bug that lives for months. This is the trace.
 */
interface ModeFallbackReporter
{
    /** @param list<string> $enabledModes the toggles that were on when nothing fitted */
    public function noApplicableMode(UserId $userId, TermId $termId, array $enabledModes): void;
}
