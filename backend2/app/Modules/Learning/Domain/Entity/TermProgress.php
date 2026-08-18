<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateInterval;
use DateTimeImmutable;

/**
 * How well a user knows one term. Keyed by (user_id, term_id) — a term learned in one
 * collection is learned everywhere. A projection folded from the append-only reviews log
 * by the {@see \App\Modules\Learning\Domain\Service\Scheduler}; immutable, so every
 * scheduling step yields a fresh instance and the fold stays a pure function.
 *
 * Two INDEPENDENT dimensions live on this entity, and every method here belongs to exactly one:
 *
 *   SCHEDULING  `state`, `easeFactor`, `intervalDays`, `dueAt`, `reps`, `lapses`,
 *               `lastReviewedAt` — written only by the Scheduler and by the two explicit
 *               `known` verification transitions.
 *   ACQUISITION `acquisition`, `learningStep`, `successfulReviews` — the ladder
 *               ({@see LearningLadder}). Written only by `introduce()`, `advanceLadder()`,
 *               `recordSuccessfulReview()` and the triage entry points.
 *
 * No method writes both. That is the whole reason the ladder could be introduced over live data
 * without recomputing an interval: `graduate` moves a pair off the recognition rungs and leaves
 * SM-2 exactly where it was, so the first real grade afterwards enters the scheduler from `new`
 * precisely as the first success of any new word always has.
 */
final class TermProgress
{
    public const DEFAULT_EASE = 2.50;

    private function __construct(
        private readonly UserId $userId,
        private readonly TermId $termId,
        private readonly LearningState $state,
        private readonly float $easeFactor,
        private readonly int $intervalDays,
        private readonly ?DateTimeImmutable $dueAt,
        private readonly int $reps,
        private readonly int $lapses,
        private readonly ?DateTimeImmutable $lastReviewedAt,
        private readonly Acquisition $acquisition = Acquisition::Graduated,
        private readonly int $learningStep = 0,
        private readonly int $successfulReviews = 0,
    ) {}

    /** A term the user has never answered: unscheduled, and standing at the intro rung. */
    public static function start(UserId $userId, TermId $termId): self
    {
        return new self(
            $userId, $termId, LearningState::New, self::DEFAULT_EASE, 0, null, 0, 0, null,
            Acquisition::New, LearningLadder::STEP_INTRO,
        );
    }

    /**
     * A term triaged as "known": self-assessed, not proven. Its `due_at` is a verification
     * check (scheduled by TriageVerificationPlanner), not an SRS interval — SM-2 never
     * touches it while known.
     */
    public static function knownFromTriage(UserId $userId, TermId $termId, DateTimeImmutable $verificationDueAt): self
    {
        return new self(
            $userId, $termId, LearningState::Known, self::DEFAULT_EASE, 0, $verificationDueAt, 0, 0, null,
            // Outside the ladder: `stepFor` returns null for a known pair and its verification is
            // always typing. `graduated` is what "the ladder has no say here" is spelled as.
            Acquisition::Graduated, 0,
        );
    }

    /**
     * A term triaged as "unsure": it goes straight onto the ladder's FIRST recognition rung,
     * skipping the intro — the learner has already seen the word during the swipe pass, so showing
     * it to them again teaches nothing.
     *
     * The scheduler is not touched: the pair is unscheduled (`new`, no `due_at`) and is selected
     * because it is on the ladder, not because it is due. The skip is a POSITION, not a flag.
     */
    public static function learningFromTriage(UserId $userId, TermId $termId, DateTimeImmutable $now): self
    {
        return new self(
            $userId, $termId, LearningState::New, self::DEFAULT_EASE, 0, null, 0, 0, null,
            Acquisition::Learning, LearningLadder::FIRST_LADDER_STEP,
        );
    }

    /**
     * The intro card was shown. The pair steps off rung 0 onto the first recognition rung, and
     * nothing else changes — no grade happened, so no scheduling field may move.
     *
     * Idempotent by design: an exposure re-uploaded from a device that lost its ack must not push
     * a pair that has since answered its recognitions back down the ladder.
     */
    public function introduce(): self
    {
        if ($this->acquisition !== Acquisition::New) {
            return $this;
        }

        return $this->withLadder(Acquisition::Learning, LearningLadder::FIRST_LADDER_STEP);
    }

    /**
     * A recognition step was answered CORRECTLY: move one rung up, and off the ladder after the
     * second one.
     *
     * Graduation writes no interval on purpose. Inventing one here would mean the ladder had an
     * opinion about WHEN the pair comes back, which is the scheduler's question; instead the pair
     * simply becomes schedulable, and the next real grade enters SM-2 from `new` exactly as the
     * first success of any new word does.
     */
    public function advanceLadder(): self
    {
        if ($this->acquisition === Acquisition::Graduated) {
            return $this;
        }

        // `new` reaches here only when the intro trainer is switched off and the pair was dealt its
        // forward-recognition card directly — so that card is what it just passed, and the rung it
        // moves to is the reverse one, same as if the intro had happened.
        return $this->acquisition === Acquisition::New || $this->learningStep <= LearningLadder::STEP_RECOGNITION_FORWARD
            ? $this->withLadder(Acquisition::Learning, LearningLadder::STEP_RECOGNITION_REVERSE)
            : $this->withLadder(Acquisition::Graduated, 0);
    }

    /**
     * A recognition step was FAILED. Everything stays where it is — that is the whole transition.
     *
     * The card is re-queued into the tail of the same session by the client, so the pair must come
     * back at the same rung; and no long SRS interval may be touched, because on the recognition
     * rungs there is no interval yet to touch and the scheduler has never seen this pair. The
     * answer itself is still appended to `reviews`: it was a real retrieval, it just failed.
     */
    public function repeatLadderStep(): self
    {
        return $this;
    }

    /**
     * A correct, non-practice review of a GRADUATED pair — the one event that widens the rungs
     * above assembly ({@see LearningLadder}).
     *
     * A ladder move and nothing else: not one scheduling field is touched here, so the counter can
     * be incremented before the scheduler runs and the two dimensions stay independent. `hard`
     * counts, `again` does not, and `again` does not reset either — see the ladder's docblock for
     * why a rung is not taken back.
     */
    public function recordSuccessfulReview(): self
    {
        return new self(
            $this->userId, $this->termId, $this->state, $this->easeFactor, $this->intervalDays,
            $this->dueAt, $this->reps, $this->lapses, $this->lastReviewedAt,
            $this->acquisition, $this->learningStep, $this->successfulReviews + 1,
        );
    }

    /** @return self */
    private function withLadder(Acquisition $acquisition, int $learningStep): self
    {
        return new self(
            $this->userId, $this->termId, $this->state, $this->easeFactor, $this->intervalDays,
            $this->dueAt, $this->reps, $this->lapses, $this->lastReviewedAt,
            $acquisition, $learningStep, $this->successfulReviews,
        );
    }

    /**
     * Return a term to `new` without erasing what we know about it: state and schedule reset,
     * but `reps`/`lapses` survive. Used when a `known` mark is undone — a term with a long
     * history that a user manually marked known must not be reduced to a blank slate. To
     * selection, a `new` row and a missing row mean the same thing, so nothing is lost by
     * keeping the row.
     *
     * On the ladder it returns to rung 0 — the pair is re-introduced from the intro. That is not a
     * demotion of anything earned: a `known` mark was a claim, never a taught word, so there is no
     * recognition step it has ever passed. `reps` survives, and so does `successfulReviews`;
     * neither is read at rung 0, and the pair will climb back through them honestly.
     */
    public function returnToNew(): self
    {
        return new self(
            $this->userId, $this->termId, LearningState::New, self::DEFAULT_EASE, 0, null,
            $this->reps, $this->lapses, $this->lastReviewedAt,
            Acquisition::New, LearningLadder::STEP_INTRO, $this->successfulReviews,
        );
    }

    /**
     * The "known" check failed — the self-assessment was wrong. Start learning the term for
     * real, due now. Written as an explicit transition (known → learning), never routed through
     * the SM-2 scheduler, whose lapse path (→ relearning) assumes an ease/interval a known
     * term never had. reps/lapses are preserved.
     */
    public function failVerification(DateTimeImmutable $now): self
    {
        // Purely a scheduling transition: the pair stays `graduated` and re-enters ordinary SM-2
        // learning. It is NOT put back on the recognition rungs — those never schedule, and a term
        // that has just proved it needs work must keep a due date, not lose one.
        return new self(
            $this->userId, $this->termId, LearningState::Learning, self::DEFAULT_EASE, 0, $now,
            $this->reps, $this->lapses, $now,
            $this->acquisition, $this->learningStep, $this->successfulReviews,
        );
    }

    /** The "known" check passed — stay known, next check a long way out. */
    public function passVerification(DateTimeImmutable $now, int $days): self
    {
        return new self(
            $this->userId, $this->termId, LearningState::Known, self::DEFAULT_EASE, 0,
            $now->add(new DateInterval('P' . $days . 'D')),
            $this->reps, $this->lapses, $now,
            $this->acquisition, $this->learningStep, $this->successfulReviews,
        );
    }

    /** Rebuild from persistence, or from the scheduler producing the next state. */
    public static function reconstitute(
        UserId $userId,
        TermId $termId,
        LearningState $state,
        float $easeFactor,
        int $intervalDays,
        ?DateTimeImmutable $dueAt,
        int $reps,
        int $lapses,
        ?DateTimeImmutable $lastReviewedAt,
        Acquisition $acquisition = Acquisition::Graduated,
        int $learningStep = 0,
        int $successfulReviews = 0,
    ): self {
        return new self(
            $userId, $termId, $state, $easeFactor, $intervalDays,
            $dueAt, $reps, $lapses, $lastReviewedAt,
            $acquisition, $learningStep, $successfulReviews,
        );
    }

    public function acquisition(): Acquisition
    {
        return $this->acquisition;
    }

    public function learningStep(): int
    {
        return $this->learningStep;
    }

    /**
     * Correct non-practice reviews of this pair since it graduated — the ladder's own counter,
     * deliberately NOT {@see reps()}, which counts scheduler calls of every grade.
     */
    public function successfulReviews(): int
    {
        return $this->successfulReviews;
    }

    /**
     * Which rung of the acquisition ladder this pair stands on, or null when it is outside it
     * (a `known` self-assessment). One derivation, {@see LearningLadder}, mirrored by the client.
     */
    public function ladderStep(): ?int
    {
        return LearningLadder::stepFor(
            $this->acquisition,
            $this->successfulReviews,
            $this->learningStep,
            isKnown: $this->state === LearningState::Known,
        );
    }

    /** Is a graded answer for this pair a ladder step (which never schedules) rather than a review? */
    public function isOnRecognitionLadder(): bool
    {
        return $this->state !== LearningState::Known && $this->acquisition->isOnLadder();
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function termId(): TermId
    {
        return $this->termId;
    }

    public function state(): LearningState
    {
        return $this->state;
    }

    public function easeFactor(): float
    {
        return $this->easeFactor;
    }

    public function intervalDays(): int
    {
        return $this->intervalDays;
    }

    public function dueAt(): ?DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function reps(): int
    {
        return $this->reps;
    }

    public function lapses(): int
    {
        return $this->lapses;
    }

    public function lastReviewedAt(): ?DateTimeImmutable
    {
        return $this->lastReviewedAt;
    }

    public function isNew(): bool
    {
        return $this->state === LearningState::New;
    }
}
