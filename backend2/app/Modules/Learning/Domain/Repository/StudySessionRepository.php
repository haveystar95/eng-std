<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\ValueObject\SessionOutcome;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

interface StudySessionRepository
{
    public function save(StudySession $session): void;

    /**
     * Close a run: stamp `ended_at` and its summary, once.
     *
     * Idempotent by construction — the write is conditional on the row still being open — because
     * the client sends this through the same offline queue its answers ride, and a queue that
     * survives a flight will re-send. A second call is a no-op, not a corrected time: the moment
     * that matters is when the learner finished, not when the phone found a network.
     *
     * @return bool  true if this call is the one that closed it
     */
    public function complete(
        StudySessionId $id,
        UserId $userId,
        DateTimeImmutable $endedAt,
        SessionOutcome $outcome,
    ): bool;
}
