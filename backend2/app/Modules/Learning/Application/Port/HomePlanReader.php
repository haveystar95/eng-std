<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\HomeNextReviewView;
use App\Modules\Learning\Application\Dto\HomeTodayView;
use App\Modules\Learning\Application\Dto\ScheduledTermFact;
use App\Modules\Learning\Application\Dto\TermErrorFact;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The Learning-owned facts the home screen's day is built from: the size of the pool, the shape of
 * the schedule just ahead, what today produced and what the last run got wrong.
 *
 * One port with several small reads rather than one read returning everything, for the same reason
 * {@see DueTermsReader} is shaped that way: each question has its own index and its own emptiness,
 * and a caller that needs three of them should pay for three, not for nine. None of them invents a
 * number — every one is a projection of `user_term_progress` or of the append-only `reviews` log.
 */
interface HomePlanReader
{
    /**
     * Which of these terms the learner has any progress row for AT ALL — «уже разобрано».
     *
     * Deliberately NOT {@see ProgressExistenceReader}, which asks whether a pair has been SHOWN
     * (an `acquisition = 'new'` row counts as not-started there, so the swipe pass re-offers a word
     * that was taken into study from the word card). That question is right for the triage deck and
     * wrong for the day's arithmetic: a word already in the pool is counted as «новое» by the
     * planner, and counting it a second time under «разобрать» would put one card into the day's
     * total twice.
     *
     * @param  list<string>  $termIds
     * @return array<string, true>  the subset that has a row, as a lookup set
     */
    public function progressTermIds(UserId $userId, array $termIds): array;

    /** Pool size: pairs with `enrolled_at IS NOT NULL`. */
    public function poolSize(UserId $userId): int;

    /** Pool pairs standing at rung 0 — enrolled, never shown, waiting for a day's new-term quota. */
    public function waitingInPool(UserId $userId): int;

    /**
     * Pool pairs whose repeat falls in the half-open window `($from, $until)`, soonest first —
     * «на грани забывания». Half-open so a horizon of N days is «the next N calendar days» whatever
     * time of day a due date happens to carry.
     *
     * The window opens AFTER `$from` on purpose: a pair already due is in today's session, and a
     * word the learner is about to be asked is not a word about to be forgotten.
     *
     * @return list<ScheduledTermFact>
     */
    public function edgeTerms(UserId $userId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit): array;

    /**
     * The next calendar day (learner's zone) that has repeats from `$from` onward — INCLUSIVE, and
     * the caller passes tomorrow's local midnight, which is exactly where a day-scale due date sits.
     * How many land on it comes with it. Null when nothing is scheduled ahead at all.
     */
    public function nextReview(UserId $userId, DateTimeImmutable $from, DateTimeZone $tz): ?HomeNextReviewView;

    /** Today's STUDY answers (non-practice) in the learner's calendar day, and the seconds they took. */
    public function todayAnswers(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz): HomeTodayView;

    /**
     * The terms most often answered WRONG in the learner's STUDY answers TODAY — the evening
     * screen's «далось труднее всего». Worst first.
     *
     * Today rather than «the last session», which is what this was written as first. A day is not
     * one session: the trainer deals twenty cards at a time, so an ordinary evening is a real run
     * followed by a two-card mop-up, and under the last-session rule the block was empty exactly on
     * the days that had something to say. The heading it sits under is «Сегодня закрыто», so the
     * day is the honest unit — and yesterday's mistakes stay out of it, which was the point of the
     * restriction in the first place.
     *
     * Practice is excluded with the rest of the day's counters: free practice never moves the plan.
     *
     * @return list<TermErrorFact>
     */
    public function hardestToday(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz, int $limit): array;

    /**
     * The learner's own seconds-per-card, from their last `$sampleSize` study answers — the honest
     * input to «≈ 9 минут». Null when there are too few answers to say.
     *
     * Implementations must be robust to the idle outlier: a phone put down mid-card records a
     * latency of minutes and is not how long a card takes.
     */
    public function averageCardSeconds(UserId $userId, int $sampleSize): ?int;

    /**
     * The last moment the learner touched any of these terms — a triage swipe or an answer.
     * Null when they have touched none of them. Backs «брошено N дней назад».
     *
     * @param  list<string>  $termIds
     */
    public function lastTouchedAt(UserId $userId, array $termIds): ?DateTimeImmutable;
}
