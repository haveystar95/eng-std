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
 * A THIRD fact rides in the same row and is independent of both: `enrolledAt` — whether this pair is
 * in the learner's personal POOL. It answers WHETHER the pair comes back at all, where the two above
 * answer when and as what. Only {@see enroll()} and {@see unenroll()} write it, and neither touches
 * anything else: leaving the pool is a pause, so the schedule and the rung are left standing exactly
 * where they were and re-entering resumes from them.
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
        private readonly ?DateTimeImmutable $enrolledAt = null,
    ) {}

    /**
     * A term the user has never answered: unscheduled, and standing at the intro rung — and NOT in
     * the pool. A row appearing because a word was met or answered is not an act of enrolment;
     * enrolment is a decision, and it is made by the triage handler or by «Учить это слово».
     */
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
     *
     * NOT enrolled: «знаю» is the one verdict that says the opposite of «учи это». The pair exists
     * so the claim can be checked, and the check rides `due_at`, not the pool.
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
            // «Не уверен» is one of the two swipes that ENROL: the learner has said, about this
            // word, that they want it worked on. That is what puts a pair in the pool — never the
            // mere existence of a row.
            Acquisition::Learning, LearningLadder::FIRST_LADDER_STEP, 0, $now,
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
            $this->acquisition, $this->learningStep, $this->successfulReviews + 1, $this->enrolledAt,
        );
    }

    /** @return self */
    private function withLadder(Acquisition $acquisition, int $learningStep): self
    {
        return new self(
            $this->userId, $this->termId, $this->state, $this->easeFactor, $this->intervalDays,
            $this->dueAt, $this->reps, $this->lapses, $this->lastReviewedAt,
            $acquisition, $learningStep, $this->successfulReviews, $this->enrolledAt,
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
            // Pool membership is NOT touched here. Undoing a `known` mark is a statement about how
            // well the word is known, not about whether it is being studied; the triage handler
            // enrols it separately, by the same rule every other «не знаю» goes through.
            Acquisition::New, LearningLadder::STEP_INTRO, $this->successfulReviews, $this->enrolledAt,
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
            $this->acquisition, $this->learningStep, $this->successfulReviews, $this->enrolledAt,
        );
    }

    /** The "known" check passed — stay known, next check a long way out. */
    public function passVerification(DateTimeImmutable $now, int $days): self
    {
        return new self(
            $this->userId, $this->termId, LearningState::Known, self::DEFAULT_EASE, 0,
            $now->add(new DateInterval('P' . $days . 'D')),
            $this->reps, $this->lapses, $now,
            $this->acquisition, $this->learningStep, $this->successfulReviews, $this->enrolledAt,
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
        ?DateTimeImmutable $enrolledAt = null,
    ): self {
        return new self(
            $userId, $termId, $state, $easeFactor, $intervalDays,
            $dueAt, $reps, $lapses, $lastReviewedAt,
            $acquisition, $learningStep, $successfulReviews, $enrolledAt,
        );
    }

    /**
     * Put this pair in the learner's pool — the deliberate act that makes it studiable.
     *
     * IDEMPOTENT, and that is the whole design of the timestamp: re-enrolling a pair that is
     * already in the pool keeps the ORIGINAL moment, so «с какого дня я это учу» is not rewritten by
     * a second tap, a replayed offline batch, or a swipe that arrives twice. Nothing else on the row
     * moves — a pair returning after a pause resumes at the rung and the due date it left with.
     */
    public function enroll(DateTimeImmutable $now): self
    {
        if ($this->enrolledAt !== null) {
            return $this;
        }

        return $this->withEnrolment($now);
    }

    /**
     * Take this pair OUT of the pool — a pause, not an erasure.
     *
     * Deliberately the smallest possible transition: one column to NULL. The review log is
     * append-only and is not touched; `state`, `due_at`, `acquisition`, `learning_step` and
     * `successful_reviews` all stay exactly as they are. That is what makes the promise the UI
     * makes — «слово можно вернуть в любой момент» — literally true rather than approximately.
     */
    public function unenroll(): self
    {
        return $this->withEnrolment(null);
    }

    /**
     * Has the app ever actually TAUGHT this pair — as opposed to the learner merely having decided
     * to study it?
     *
     * The question a triage verdict has to ask before it overwrites anything. Enrolment creates a
     * row before the word has ever been shown, so «does a row exist» stopped being the same question
     * and a `known` swipe on an enrolled-but-unmet word would either clobber real progress (if the
     * guard were dropped) or do nothing at all (if it stayed).
     *
     * Untaught means all three: standing at rung 0, the scheduler has never run for it, and it has
     * never been answered. Anything else is a history a swipe must not rewrite.
     */
    public function hasBeenTaught(): bool
    {
        return $this->acquisition !== Acquisition::New
            || $this->reps > 0
            || $this->lastReviewedAt !== null;
    }

    /** Is this pair in the pool, i.e. may the trainer deal it at all? */
    public function isEnrolled(): bool
    {
        return $this->enrolledAt !== null;
    }

    public function enrolledAt(): ?DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    private function withEnrolment(?DateTimeImmutable $enrolledAt): self
    {
        return new self(
            $this->userId, $this->termId, $this->state, $this->easeFactor, $this->intervalDays,
            $this->dueAt, $this->reps, $this->lapses, $this->lastReviewedAt,
            $this->acquisition, $this->learningStep, $this->successfulReviews, $enrolledAt,
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
