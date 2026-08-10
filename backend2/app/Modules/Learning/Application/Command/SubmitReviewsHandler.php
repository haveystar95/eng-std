<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\ReviewBatchResult;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Learning\Application\Dto\SessionContext;
use App\Modules\Learning\Application\Port\LatencyMedianReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Application\Port\SessionContextReader;
use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Event\ReviewsSubmitted;
use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\ReviewRepository;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Service\AnswerGrader;
use App\Modules\Learning\Domain\Service\Scheduler;
use App\Modules\Learning\Domain\ValueObject\Answer;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ExpectedAnswer;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use DateTimeImmutable;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;

/**
 * Applies an answer batch. The SERVER grades each raw answer (leniency + per-mode latency
 * median), so the grading rule lives in one runtime, then folds the accepted answers into
 * (user, term) progress in answered_at order — a replayed offline batch yields the same
 * progress it would have online. Practice answers are appended to the log (they keep the
 * streak) but never schedule. The per-mode median cache is invalidated after the fold.
 */
final readonly class SubmitReviewsHandler
{
    /** How far out the next check is pushed when a "known" verification passes. */
    private const VERIFICATION_PASS_DAYS = 90;

    public function __construct(
        private ReviewRepository $reviews,
        private TermProgressRepository $progress,
        private Scheduler $scheduler,
        private TermExistenceReader $terms,
        private TermAnswerKeyReader $answerKeys,
        private AnswerGrader $grader,
        private LatencyMedianReader $median,
        private SessionContextReader $sessionContexts,
        private StudySessionRepository $sessions,
        private ProgressSnapshotReader $snapshots,
        private StatsProjector $stats,
        private LearnerProfileReader $profile,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(SubmitReviews $command): ReviewBatchResult
    {
        $known = $this->knownTermIds($command);
        $keys = $this->answerKeys->byIds($command->termIds());
        $contexts = $this->sessionContexts->byIds($command->sessionIds());
        // Practice sessions the device built while offline: the server has never seen the id, so
        // it is adopted below rather than rejected (see [adoptOfflinePracticeSessions]).
        $adoptable = $this->adoptableSessions($command, $contexts);
        // Pre-batch states, so an answer can be flagged as a verification (the term was `known`
        // when answered) — a fact that is gone once the fold changes the state.
        $states = $this->snapshots->forTerms(
            $command->actorId,
            array_map(static fn (TermId $id): string => $id->value, $command->termIds()),
        );

        return $this->tx->run(function () use ($command, $known, $keys, $contexts, $adoptable, $states): ReviewBatchResult {
            // Must happen before any review row is written: reviews.session_id has a foreign key
            // to study_sessions, so an answer naming an unknown session would fail the insert.
            $contexts += $this->adoptOfflinePracticeSessions($command, $adoptable);
            /** @var list<Review> $accepted */
            $accepted = [];
            /** @var array<string, ExerciseMode> $touchedModes */
            $touchedModes = [];
            $duplicates = 0;
            $unknown = 0;

            foreach ($command->reviews as $input) {
                if (! isset($known[$input->termId->value])) {
                    $unknown++;

                    continue;
                }
                $key = $keys[$input->termId->value] ?? null;
                if ($key === null) {
                    $unknown++;

                    continue;
                }
                if ($input->sessionId !== null && ! $this->sessionAccepts($input, $contexts, $command)) {
                    $unknown++;

                    continue;
                }

                $grade = $this->grader->grade(
                    new Answer($input->response, $input->usedHint, $input->latencyMs),
                    $input->exerciseMode,
                    new ExpectedAnswer($key->accepted, $key->isPhrase),
                    // Cached per (user, mode); frozen for this batch, invalidated below.
                    $this->median->medianFor($command->actorId, $input->exerciseMode),
                );

                // A verification is a non-practice answer to a term that was `known`. Practice
                // answers are never verifications — free training doesn't resolve the check.
                $isVerification = ! $input->isPractice
                    && (($states[$input->termId->value] ?? null)?->state === LearningState::Known);

                $review = new Review(
                    id: $input->reviewId,
                    userId: $command->actorId,
                    termId: $input->termId,
                    grade: $grade,
                    answeredAt: $input->answeredAt,
                    clientSeq: $input->clientSeq,
                    exerciseMode: $input->exerciseMode,
                    isPractice: $input->isPractice,
                    isVerification: $isVerification,
                    response: $input->response,
                    sessionId: $input->sessionId,
                    latencyMs: $input->latencyMs,
                );

                if (! $this->reviews->insertIgnore($review)) {
                    $duplicates++;

                    continue;
                }

                $accepted[] = $review;
                $touchedModes[$input->exerciseMode->value] = $input->exerciseMode;
            }

            $introduced = $this->foldIntoProgress($command, $accepted);

            if ($accepted !== []) {
                $this->stats->project(new ReviewsSubmitted($this->clock->now(), $accepted, $introduced));
            }

            foreach ($touchedModes as $mode) {
                $this->median->forget($command->actorId, $mode);
            }

            return new ReviewBatchResult(
                accepted: count($accepted),
                duplicates: $duplicates,
                unknown: $unknown,
            );
        });
    }

    /**
     * May this answer be recorded against the session it names?
     *
     * Two different jobs, deliberately separated:
     *
     *  * OWNERSHIP — an answer is never accepted against another user's session. No exceptions,
     *    practice included.
     *  * COMPOSITION — an answer must be for a term the session was built with, so an abandoned
     *    session plus a retry cannot push out-of-context terms and spend the daily new-word quota
     *    on words the user never saw. That protects SCHEDULING, so it does not apply to practice,
     *    which schedules nothing. It is skipped for practice entirely — not even checked against
     *    an adopted session, because a second chunk of the same offline run legitimately carries
     *    terms the first chunk never mentioned.
     *
     * A practice answer against the user's own SCHEDULING session is still rejected: the two kinds
     * of run are not interchangeable, and mixing them would corrupt the history the session groups.
     *
     * @param  array<string, SessionContext>  $contexts
     */
    private function sessionAccepts(ReviewInput $input, array $contexts, SubmitReviews $command): bool
    {
        if ($input->sessionId === null) {
            return true; // no session named — nothing to check
        }
        $context = $contexts[$input->sessionId->value] ?? null;
        if ($context === null) {
            return false; // unknown session — an offline practice one would have been adopted
        }
        if ($context->userId !== $command->actorId->value) {
            return false;
        }
        if ($input->isPractice) {
            return $context->isPractice;
        }

        return $context->has($input->termId->value);
    }

    /**
     * Practice sessions this batch names that the server has never seen — i.e. built on the device
     * while offline. Grouped here, outside the transaction, so the write below is a plain insert.
     *
     * Only practice qualifies. An unknown SCHEDULING session stays unknown: its composition is the
     * guard on the daily quota, and a client that could mint one could mint the quota away with it.
     *
     * @param  array<string, SessionContext>  $contexts
     * @return array<string, array{terms: list<TermId>, startedAt: DateTimeImmutable}>
     */
    private function adoptableSessions(SubmitReviews $command, array $contexts): array
    {
        $out = [];
        foreach ($command->reviews as $input) {
            if ($input->sessionId === null || ! $input->isPractice) {
                continue;
            }
            $sessionId = $input->sessionId->value;
            if (isset($contexts[$sessionId])) {
                continue;
            }
            if (! isset($out[$sessionId])) {
                $out[$sessionId] = ['terms' => [], 'startedAt' => $input->answeredAt];
            }
            $out[$sessionId]['terms'][] = $input->termId;
            if ($input->answeredAt < $out[$sessionId]['startedAt']) {
                $out[$sessionId]['startedAt'] = $input->answeredAt;
            }
        }

        return $out;
    }

    /**
     * Create the study_sessions rows for [adoptableSessions], and return their contexts so the
     * loop treats them as the user's own practice runs.
     *
     * The write is `insertOrIgnore` (see EloquentStudySessionRepository), which is what makes two
     * chunks of the same offline run safe: the second one finds the row already there and neither
     * fails nor overwrites the first chunk's `started_at` or composition.
     *
     * @param  array<string, array{terms: list<TermId>, startedAt: DateTimeImmutable}>  $adoptable
     * @return array<string, SessionContext>
     */
    private function adoptOfflinePracticeSessions(SubmitReviews $command, array $adoptable): array
    {
        $adopted = [];
        foreach ($adoptable as $sessionId => $session) {
            $this->sessions->save(StudySession::start(
                id: StudySessionId::fromString($sessionId),
                userId: $command->actorId,
                isPractice: true,
                composition: $session['terms'],
                startedAt: $session['startedAt'],
            ));
            $composition = [];
            foreach ($session['terms'] as $termId) {
                $composition[$termId->value] = true;
            }
            $adopted[$sessionId] = new SessionContext(
                userId: $command->actorId->value,
                isPractice: true,
                composition: $composition,
            );
        }

        return $adopted;
    }

    /**
     * Fold accepted, non-practice answers into progress, in client_seq order, one term at a time.
     * Ordering by the per-user monotonic client_seq (not answered_at, a device clock) means a
     * replayed or clock-skewed offline batch yields the same progress it would have online.
     * Practice answers are skipped: they never affect intervals, state or lapses.
     *
     * @param  list<Review>  $accepted
     * @return list<string>  ids of terms answered (for real) for the first time
     */
    private function foldIntoProgress(SubmitReviews $command, array $accepted): array
    {
        // Practice answers are dropped here: they never touch progress — and, deliberately, an
        // answer to a `known` term in a practice session does NOT resolve its verification. Free
        // training has no stake, so it must not settle the "do you really know this" check.
        $scheduled = array_values(array_filter($accepted, static fn (Review $r): bool => ! $r->isPractice));
        usort($scheduled, static fn (Review $a, Review $b): int => $a->clientSeq <=> $b->clientSeq);

        // The user's calendar zone, read once per batch — the scheduler floors day-scale due dates
        // to the start of the user's day in it (F19).
        $zone = $this->profile->timezoneFor($command->actorId);

        /** @var array<string, list<Review>> $byTerm */
        $byTerm = [];
        foreach ($scheduled as $review) {
            $byTerm[$review->termId->value][] = $review;
        }

        $introduced = [];
        foreach ($byTerm as $termReviews) {
            $termId = $termReviews[0]->termId;

            $termProgress = $this->progress->findForUpdate($command->actorId, $termId);
            if ($termProgress === null) {
                $termProgress = TermProgress::start($command->actorId, $termId);
                $introduced[] = $termId->value;
            }

            foreach ($termReviews as $review) {
                // An answer to a `known` term is its verification check, not an SRS review — the
                // scheduler refuses `known`, so resolve pass/fail explicitly here.
                $termProgress = $termProgress->state() === LearningState::Known
                    ? $this->resolveVerification($termProgress, $review->grade, $review->answeredAt)
                    : $this->scheduler->schedule($termProgress, $review->grade, $review->answeredAt, $zone);
            }

            $this->progress->save($termProgress);
        }

        return $introduced;
    }

    /** A wrong verification answer sends a known term to learning; anything correct keeps it known. */
    private function resolveVerification(TermProgress $progress, Grade $grade, DateTimeImmutable $answeredAt): TermProgress
    {
        return $grade === Grade::Again
            ? $progress->failVerification($answeredAt)
            : $progress->passVerification($answeredAt, self::VERIFICATION_PASS_DAYS);
    }

    /** @return array<string, true> */
    private function knownTermIds(SubmitReviews $command): array
    {
        $set = [];
        foreach ($this->terms->existing($command->termIds()) as $termId) {
            $set[$termId->value] = true;
        }

        return $set;
    }
}
