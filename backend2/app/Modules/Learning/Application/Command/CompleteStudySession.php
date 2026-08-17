<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Close a study run the learner has played to its summary screen.
 *
 * The client supplies only WHEN — everything else about the run is already in the server's own
 * logs. `endedAt` comes from the device because the session may well have finished in airplane
 * mode, and the honest time is the one the learner stopped, not the one the queue drained.
 */
final readonly class CompleteStudySession
{
    public function __construct(
        public UserId $actorId,
        public StudySessionId $sessionId,
        public DateTimeImmutable $endedAt,
    ) {}
}
