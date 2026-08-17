<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\SessionOutcome;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;

/**
 * What a run actually produced, read back off its own logs — the reviews and exposures that name
 * the session.
 *
 * A port rather than a query on the repository because it reads two append-only tables to answer
 * one question, and because it is the whole reason the client never has to be trusted with the
 * summary: the server can always recompute it, and a session closed twice recomputes to the same
 * numbers.
 */
interface SessionOutcomeReader
{
    public function forSession(StudySessionId $sessionId): SessionOutcome;
}
