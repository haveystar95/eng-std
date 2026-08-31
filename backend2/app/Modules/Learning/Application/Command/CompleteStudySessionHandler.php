<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\SessionOutcomeReader;
use App\Modules\Learning\Application\Service\PlanDayPassing;
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
 *
 * ## The one thing that DOES happen here, and why
 *
 * «Этот день плана пройден» is re-judged ({@see PlanDayPassing}). Nothing about progress moves —
 * the answers were folded when they were uploaded and the verdict is derived from them — but until
 * now it was only ever WRITTEN while building the next plan session. A learner who finished day 1
 * and went back to the home screen therefore saw a plan that still said «День 1 из 5», and day 2
 * was not queued: the verdict existed and nobody had asked for it. The end of a sitting is the
 * other moment it is true, so it is asked for here too.
 *
 * It runs after the close and never before it: a session that did not close did not end, and a
 * learner with no running plan pays one indexed lookup for the question.
 */
final readonly class CompleteStudySessionHandler
{
    public function __construct(
        private StudySessionRepository $sessions,
        private SessionOutcomeReader $outcomes,
        private PlanDayPassing $planDays,
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
        $closed = $this->sessions->complete(
            $command->sessionId,
            $command->actorId,
            $command->endedAt,
            $outcome,
        );

        if ($closed) {
            $this->planDays->refreshActiveFor($command->actorId);
        }

        return $closed;
    }
}
