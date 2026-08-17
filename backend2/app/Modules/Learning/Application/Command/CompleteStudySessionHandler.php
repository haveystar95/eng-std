<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\SessionOutcomeReader;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;

/**
 * Stamps `ended_at` and the run's summary on a session the learner played to its end.
 *
 * Nothing about progress happens here: the answers were folded when they were uploaded, and a
 * session is a grouping for reporting, never a scheduling input. Closing one only makes the
 * difference between «played» and «abandoned» readable — before this there was no writer for the
 * column at all, so every run in the table looked abandoned (QA-12).
 *
 * ABANDONED RUNS ARE LEFT OPEN, deliberately: a session that produced nothing is not closed with a
 * row of zeroes, because `ended_at IS NULL` is then the true statement about it. That keeps the
 * column answering the question it exists for.
 */
final readonly class CompleteStudySessionHandler
{
    public function __construct(
        private StudySessionRepository $sessions,
        private SessionOutcomeReader $outcomes,
    ) {}

    /** @return bool  true when this call is the one that closed the session */
    public function __invoke(CompleteStudySession $command): bool
    {
        $outcome = $this->outcomes->forSession($command->sessionId);
        if ($outcome->isEmpty()) {
            return false;
        }

        // Ownership and the once-only rule both live in the conditional write — one statement, so
        // two devices finishing the same run cannot race past each other's check.
        return $this->sessions->complete(
            $command->sessionId,
            $command->actorId,
            $command->endedAt,
            $outcome,
        );
    }
}
