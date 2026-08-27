<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\ExposureInput;
use App\Modules\Learning\Application\Dto\ReviewBatchResult;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Learning\Application\Dto\SessionContext;
use App\Modules\Learning\Application\Port\LatencyMedianReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Application\Port\SessionContextReader;
use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Learning\Domain\Entity\TermExposure;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Event\ReviewsSubmitted;
use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\ReviewRepository;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\Repository\TermExposureRepository;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Service\AnswerGrader;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\Service\Scheduler;
use App\Modules\Learning\Domain\Service\SpokenCoverage;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\Answer;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ExpectedAnswer;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\MatchPolicy;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use DateTimeImmutable;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermAnswerKeyView;
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
        private TermExposureRepository $exposures,
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
            // Intros first: an intro and the recognition it leads to legitimately arrive in one
            // batch, and the answer must be folded against a pair that has already been introduced.
            $exposed = $this->recordExposures($command, $known, $contexts);
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

                // An intro carries no answer, so it can never arrive as a review. If one does, the
                // client is confused about its own card; refusing it is what stops a retrieval that
                // never happened from entering the log.
                if (! $input->exerciseMode->isGraded()) {
                    $unknown++;

                    continue;
                }

                // A recognition-rung answer that arrives after the pair has left the ladder — see
                // isStaleLadderAnswer(). Skipped rather than graded, so a correct tap can never be
                // turned into a lapse.
                if ($this->isStaleLadderAnswer($input, $states)) {
                    $unknown++;

                    continue;
                }

                $grade = $this->grader->grade(
                    new Answer($input->response, $input->usedHint, $input->latencyMs),
                    $input->exerciseMode,
                    $this->expectedFor($input, $key, $states),
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
                    ladderStep: $input->ladderStep,
                );

                if (! $this->reviews->insertIgnore($review)) {
                    $duplicates++;

                    continue;
                }

                $accepted[] = $review;
                $touchedModes[$input->exerciseMode->value] = $input->exerciseMode;
            }

            $introduced = $this->foldIntoProgress($command, $accepted);

            if ($accepted !== [] || $exposed !== []) {
                $this->stats->project(new ReviewsSubmitted($this->clock->now(), $accepted, $introduced, $exposed));
            }

            foreach ($touchedModes as $mode) {
                $this->median->forget($command->actorId, $mode);
            }

            return new ReviewBatchResult(
                accepted: count($accepted),
                duplicates: $duplicates,
                unknown: $unknown,
                exposures: count($exposed),
            );
        });
    }

    /**
     * What this answer is graded against. Word-level modes are checked against the term's own
     * accepted forms; a sentence-level mode (scramble) is checked against the term's PINNED example
     * — the very sentence its card was built from, which the answer key carries for exactly this.
     *
     * Which of the two a card asked for is READ OFF THE RUNG the client echoed back, because
     * `speaking` asks for the word early and for the example late and the mode alone cannot say
     * which. That is the same `ladder_step` the recognition path below already trusts, and it is
     * trustworthy for the same reason: it is a statement about the card that was SHOWN, which the
     * server cannot reconstruct after the pair has moved.
     *
     * `isPhrase` is forced true for the sentence case: the latency thresholds are per shape, and a
     * sentence held to the single-word "slow" bound (8 s) would grade an honest assembly as `hard`.
     *
     * A term that lost its example between the card and the upload falls back to the term forms —
     * the assembled sentence then won't match and grades `again`. Rare (it takes a "New example"
     * mid-session) and honest: we never invent a key the learner wasn't shown.
     *
     * A near-SYNONYM of the term joins the key on the cards whose prompt is the MEANING — the
     * translation, or the description — and on no others. `goal` answers «цель» as well as `purpose`
     * does, and marking it wrong both fails a learner for knowing the language and wipes the
     * interval; but somebody who HEARD /ˈpɜːpəs/ and typed `goal` did not answer the question that
     * card asked. See {@see ExerciseMode::acceptsSynonyms()} for the whole of that split.
     *
     * The one exception is the FORWARD-RECOGNITION card (ladder rung 1), which is graded by
     * IDENTITY: it shows the term and offers translations to tap, so the client uploads the tapped
     * option's id and the key is this card's own term id. No translation string is ever compared,
     * which is what keeps the answer-key rule («never a translation in a text key») literally true
     * — see `.claude/skills/learning-srs`.
     *
     * @param  array<string, \App\Modules\Learning\Application\Dto\DueTermView>  $states  pre-batch snapshot
     */
    private function expectedFor(ReviewInput $input, TermAnswerKeyView $key, array $states): ExpectedAnswer
    {
        if ($this->isForwardRecognition($input, $states)) {
            return new ExpectedAnswer([$input->termId->value]);
        }

        if ($input->exerciseMode->gradesAgainstExample($input->ladderStep)
            && $key->example !== null
            && trim($key->example) !== '') {
            return new ExpectedAnswer([$key->example], isPhrase: true, policy: $this->policyForExample($input));
        }

        // WORD-LEVEL. The key is the term's own forms, plus its near-synonyms where the card asked
        // what the word MEANS ({@see ExerciseMode::acceptsSynonyms()}) and not where it asked for
        // this word in particular. Both halves are target-language text the term itself owns, so the
        // answer-key rule («never a translation in a text key») is untouched.
        //
        // Not folded into `$key->accepted` by the reader, deliberately: the two are accepted on
        // different cards, and a reader that merged them would make the distinction unrepresentable
        // and hand a listening card a word the learner never heard.
        return new ExpectedAnswer(
            $input->exerciseMode->acceptsSynonyms()
                ? [...$key->accepted, ...$key->synonyms]
                : $key->accepted,
            $key->isPhrase,
            $this->policyForTerm($input, $key),
        );
    }

    /**
     * How the SENTENCE key is compared. Every mode that assembles, types or taps a sentence is
     * compared for equality, because the learner produced every character of it. `speaking` is not:
     * its answer came out of a speech recogniser, which drops and swaps words on a perfectly good
     * reading, so it is compared by COVERAGE ({@see \App\Modules\Learning\Domain\Service\SpokenCoverage} for the numbers and the
     * reasoning). Being wrong in the strict direction here is not a cosmetic bug — an exact match
     * on a transcript delivers a LAPSE for a room that was noisy.
     */
    private function policyForExample(ReviewInput $input): MatchPolicy
    {
        return $input->exerciseMode === ExerciseMode::Speaking ? MatchPolicy::Coverage : MatchPolicy::Exact;
    }

    /**
     * How the WORD-LEVEL key is compared — the same question one step down, and the answer is the
     * same wherever a microphone was involved and the thing said was long.
     *
     * A term is not always a word. Asked to say «Where do you see yourself in five years?» the
     * learner reads a whole sentence into the microphone, and the recogniser mangles it exactly as
     * it mangles a pinned example; holding it to equality graded a good reading `again`
     * (BUGFIX-2 Ч.3б). So a spoken answer to a LONG term is compared by coverage, which in practice
     * says «the words of the card are in what was heard» — {@see SpokenCoverage::LONG_UTTERANCE_WORDS}.
     *
     * A short term keeps equality, and deliberately: coverage over a one-word key would accept the
     * word buried in any sentence at all, and the normalising stages ({@see AnswerGrader}) already
     * forgive the article, the punctuation and — for speaking alone — a dropped trailing sibilant,
     * which is what a one-word reading actually loses.
     *
     * Everything typed, assembled or tapped stays `exact` whatever its length: the learner produced
     * every character of those.
     */
    private function policyForTerm(ReviewInput $input, TermAnswerKeyView $key): MatchPolicy
    {
        return $input->exerciseMode === ExerciseMode::Speaking
            && SpokenCoverage::isLongUtterance($key->accepted[0] ?? '')
                ? MatchPolicy::Coverage
                : MatchPolicy::Exact;
    }

    /**
     * Is this answer a tap on a forward-recognition card?
     *
     * Three things must agree, because the rung is a CLAIM by the client and identity grading is
     * the one path where a bare id counts as a correct answer:
     *
     *  * the claimed rung is 1, and this is not practice (free training is off the ladder);
     *  * the MODE is multiple_choice — rung 1 is a tap, and only a tap. Without this, a batch
     *    claiming `ladder_step: 1` under `typing` would have a tapped id graded as production,
     *    where a fast answer can earn `easy` and skew that mode's latency median;
     *  * the pair was genuinely still on the ladder before this batch (a missing row qualifies —
     *    that is a first meeting, which is exactly where rung 1 lives).
     *
     * @param  array<string, \App\Modules\Learning\Application\Dto\DueTermView>  $states
     */
    private function isForwardRecognition(ReviewInput $input, array $states): bool
    {
        if ($input->ladderStep !== LearningLadder::STEP_RECOGNITION_FORWARD
            || $input->isPractice
            || $input->exerciseMode !== ExerciseMode::MultipleChoice) {
            return false;
        }

        return $this->isOnLadder($input, $states);
    }

    /**
     * Was this pair still on the recognition rungs before this batch?
     *
     * @param  array<string, \App\Modules\Learning\Application\Dto\DueTermView>  $states
     */
    private function isOnLadder(ReviewInput $input, array $states): bool
    {
        $snapshot = $states[$input->termId->value] ?? null;

        return $snapshot === null || $snapshot->acquisition !== Acquisition::Graduated;
    }

    /**
     * A ladder answer for a pair that is no longer on the ladder is DROPPED, not graded as text.
     *
     * It takes two devices interleaving uploads of the same word's ladder cards: one finishes the
     * ladder, the other then sends a rung-1 tap. Grading that as text would compare a term id
     * against the term's own forms, fail, and — the pair now being graduated — deliver that `again`
     * to the scheduler as a LAPSE. A correct tap must never cost an interval, and the answer cannot
     * be graded honestly against a card the server can no longer reconstruct, so it is skipped.
     *
     * @param  array<string, \App\Modules\Learning\Application\Dto\DueTermView>  $states
     */
    private function isStaleLadderAnswer(ReviewInput $input, array $states): bool
    {
        return $input->ladderStep !== null
            && LearningLadder::isRecognitionStep($input->ladderStep)
            && ! $input->isPractice
            && ! $this->isOnLadder($input, $states);
    }

    /**
     * Append the batch's intro cards and step each pair onto the first recognition rung.
     *
     * Nothing here is graded, nothing reaches `reviews`, and no scheduling field is written. The
     * write is an ignored insert on `(user, term)`, so a re-uploaded batch changes nothing and the
     * FIRST `shown_at` survives — and only a NEWLY inserted row advances the ladder, which is what
     * keeps the two idempotent together.
     *
     * @param  array<string, true>  $known
     * @param  array<string, SessionContext>  $contexts
     * @return list<TermExposure>  the exposures newly recorded
     */
    private function recordExposures(SubmitReviews $command, array $known, array $contexts): array
    {
        $recorded = [];
        foreach ($command->exposures as $input) {
            if (! isset($known[$input->termId->value])) {
                continue;
            }

            // An unknown session is dropped from the exposure rather than dropping the exposure:
            // the fact that matters is that the learner met the word, and `session_id` has a
            // foreign key that a session the server has never seen would break.
            $sessionId = $input->sessionId;
            if ($sessionId !== null) {
                $context = $contexts[$sessionId->value] ?? null;
                if ($context === null || $context->userId !== $command->actorId->value) {
                    $sessionId = null;
                }
            }

            $exposure = new TermExposure(
                userId: $command->actorId,
                termId: $input->termId,
                shownAt: $input->shownAt,
                sessionId: $sessionId,
            );

            if (! $this->exposures->insertIgnore($exposure)) {
                continue; // already met — the ladder has moved on since, and must not move back
            }

            $progress = $this->progress->findForUpdate($command->actorId, $input->termId)
                ?? TermProgress::start($command->actorId, $input->termId);
            $this->progress->save($progress->introduce());

            $recorded[] = $exposure;
        }

        return $recorded;
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
            // «Introduced today» is a question about the ACQUISITION LADDER — did this answer take
            // the pair off rung 0 — not about whether a row happened to exist. It used to be read
            // off row existence, which agreed with the ladder only while the first answer was also
            // the first write; enrolling a word now creates its row before it has ever been shown,
            // and reading existence would report a day's whole new-word goal as already spent by the
            // act of choosing what to learn.
            //
            // An intro card earlier in this same batch has already moved the pair off rung 0, so it
            // is not counted twice: the exposure counted it (see EloquentDailyStatsProjector).
            $introduce = $termProgress === null || $termProgress->acquisition() === Acquisition::New;
            $termProgress ??= TermProgress::start($command->actorId, $termId);
            if ($introduce) {
                $introduced[] = $termId->value;
            }

            foreach ($termReviews as $review) {
                // THE LADDER'S COUNTER, and the only place it is written.
                //
                // One rule, applied before the branches below split, because the three of them
                // agree about it: a correct answer to a pair that is already GRADUATED is a
                // successful review. On the recognition rungs the pair is not graduated, so a
                // passed step does not count — it moves `learning_step` instead, which is the
                // honest measure there. `again` does not count and does not reset.
                //
                // A `known` verification passes through here too, and deliberately: it is a
                // correct, non-practice retrieval of a graduated pair, so it is exactly what the
                // counter is for — and it is the same population the migration's backfill counts,
                // which is what keeps a replayed history and a live batch in agreement.
                //
                // Practice never reaches this loop: it was filtered out above, where the reason
                // is written down.
                if ($review->grade !== Grade::Again && $termProgress->acquisition() === Acquisition::Graduated) {
                    $termProgress = $termProgress->recordSuccessfulReview();
                }

                // An answer to a `known` term is its verification check, not an SRS review — the
                // scheduler refuses `known`, so resolve pass/fail explicitly here.
                if ($termProgress->state() === LearningState::Known) {
                    $termProgress = $this->resolveVerification($termProgress, $review->grade, $review->answeredAt);

                    continue;
                }

                // A RECOGNITION-RUNG answer moves the ladder and nothing else. It is a real
                // retrieval — it is in the log above, it keeps the streak, it counts in the day's
                // stats — but the scheduler has never seen this pair and must not start now:
                // graduation invents no interval, and a failed step invents nothing at all so the
                // client can re-queue the same card into the tail of the session.
                if ($termProgress->isOnRecognitionLadder()) {
                    $termProgress = $review->grade === Grade::Again
                        ? $termProgress->repeatLadderStep()
                        : $termProgress->advanceLadder();

                    continue;
                }

                $termProgress = $this->scheduler->schedule($termProgress, $review->grade, $review->answeredAt, $zone);
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
