<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\HomeNextReviewView;
use App\Modules\Learning\Application\Dto\HomeTodayView;
use App\Modules\Learning\Application\Dto\ScheduledTermFact;
use App\Modules\Learning\Application\Dto\TermPromotionFact;
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

    /**
     * How many cards the trainer owes RIGHT NOW — the day's repeats, not one sitting's worth.
     *
     * The same population {@see DueTermsReader::selectableInPool()} deals, counted instead of
     * materialised: pool pairs unfinished on the ladder or due (or never scheduled), plus a `known`
     * verification whose check has come due. So «повторить N» is 0 exactly when a session would come
     * back empty, which is the property that matters, and N is the honest size of the backlog.
     *
     * Counted rather than read through the session builder ON PURPOSE. Asking the builder means
     * asking it for a SESSION, and a session is capped at its size — which made the card say
     * «20 повторить» to a learner with sixty due, and made the number sit still through two runs
     * because the cap refilled from the backlog each time.
     */
    public function owedCount(UserId $userId, DateTimeImmutable $now): int;

    /**
     * The SAME population as {@see owedCount()}, counted in CARDS rather than in words.
     *
     * A word and a card are not the same unit, and the day card promises one while the session
     * deals the other: a pair still on the recognition rungs owes the rest of its chain in one
     * sitting ({@see LearningLadder::chainLength()}), so twenty owed words can be thirty cards.
     * Both numbers exist because both are true and the learner needs both — «сколько слов» is the
     * work, «сколько карточек» is the time.
     *
     * NEW words are not here, exactly as they are not in `owedCount`: their chain length depends on
     * whether the intro trainer is switched on, which is the caller's fact and not this reader's.
     */
    public function owedCardCount(UserId $userId, DateTimeImmutable $now): int;

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
     * How many of the learner's OWN words fall due TOMORROW — «Завтра выпадет 14 слов →».
     *
     * The whole of tomorrow, in their calendar: `$tomorrowStart` is tomorrow's local midnight and the
     * window closes at the midnight after it. A narrower question than {@see nextReview()}, which
     * finds the next day that has ANYTHING and may answer with a date a week out — the row on the
     * screen promises tomorrow specifically, so it is counted specifically.
     *
     * The pool only, exactly as {@see edgeTerms()} counts it: a `known` verification riding beside
     * the pool is the system auditing a claim, not a word of the learner's coming back.
     */
    public function dueTomorrowCount(UserId $userId, DateTimeImmutable $tomorrowStart, DateTimeZone $tz): int;

    /**
     * The pairs that ROSE A RUNG today — «+5 слов продвинулись · reluctant дошло до „написание"».
     *
     * Derived, with no new column and no new log. The rung a pair stands on NOW is
     * {@see \App\Modules\Learning\Domain\Service\LearningLadder::stepFor()} over its progress row;
     * the rung it started the day on is the `ladder_step` the append-only review log recorded for the
     * FIRST card dealt today — the log already says what was shown, and what was shown is where the
     * day began. A pair whose two rungs differ moved.
     *
     * Study answers only, like every other number the day is made of: free practice moves no rung, so
     * it can produce no promotion.
     *
     * Rows with no `ladder_step` are skipped rather than guessed. That column is null only for the
     * pre-ladder history, all of it older than any «today» this is asked about.
     *
     * @return list<TermPromotionFact>  worst-to-best is not implied; the caller picks its example
     */
    public function promotionsToday(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz): array;

    /**
     * How many pairs reached «выучено» — graduated off the recognition rungs — since `$since`.
     *
     * The statistics tile's middle number («за неделю 28»). Counted from the review log rather than
     * from a `graduated_at` column, which does not exist: a pair is graduated from the moment it is
     * first dealt a card ABOVE the recognition rungs, so its graduation day is the day of its first
     * such card, and «since» is a filter on that day.
     *
     * The same rung predicate the `successful_reviews` backfill uses, null included: a null
     * `ladder_step` is pre-ladder history, and all of that was graduated.
     */
    public function graduatedSince(UserId $userId, DateTimeImmutable $since): int;

    /**
     * The learner's own seconds-per-card, from their last `$sampleSize` study answers — the honest
     * input to «≈ 9 минут». Null when there are too few answers to say.
     *
     * Implementations must be robust to the idle outlier: a phone put down mid-card records a
     * latency of minutes and is not how long a card takes.
     */
    public function averageCardSeconds(UserId $userId, int $sampleSize): ?int;

    /**
     * The learner's own seconds per SWIPE, from their last `$sampleSize` triage decisions.
     * Null when there are too few to say.
     *
     * A separate figure from {@see averageCardSeconds()} because a swipe is a different act and the
     * measurements say so plainly: 3.0 s against 8–11 s in the development database. The day's
     * estimate needs both — a hundred-word swipe pass priced as a hundred exercises turns «~20
     * минут» into «~40» and makes the one number the research called most useful into a deterrent.
     */
    public function averageSwipeSeconds(UserId $userId, int $sampleSize): ?int;

    /**
     * The last moment the learner touched any of these terms — a triage swipe or an answer.
     * Null when they have touched none of them. Backs «брошено N дней назад».
     *
     * @param  list<string>  $termIds
     */
    public function lastTouchedAt(UserId $userId, array $termIds): ?DateTimeImmutable;
}
