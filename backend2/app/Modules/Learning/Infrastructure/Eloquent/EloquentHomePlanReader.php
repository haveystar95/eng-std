<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\HomeNextReviewView;
use App\Modules\Learning\Application\Dto\HomeTodayView;
use App\Modules\Learning\Application\Dto\ScheduledTermFact;
use App\Modules\Learning\Application\Dto\TermErrorFact;
use App\Modules\Learning\Application\Dto\TermPromotionFact;
use App\Modules\Learning\Application\Port\HomePlanReader;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class EloquentHomePlanReader implements HomePlanReader
{
    private const PROGRESS = 'user_term_progress';

    /**
     * A card the learner walked away from is not a card that took eleven minutes. The average is
     * built from latencies capped at this ceiling — a winsorised mean, which keeps a genuinely slow
     * card in the figure and keeps the phone-left-on-the-table one from owning it.
     */
    private const LATENCY_CEILING_MS = 60_000;

    /** Below this many answers the learner has no personal pace yet and the caller uses its default. */
    private const MIN_SAMPLES = 20;

    /** Sanity bounds on the derived per-card figure, in seconds. */
    private const MIN_CARD_SECONDS = 3;
    private const MAX_CARD_SECONDS = 30;

    public function progressTermIds(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table(self::PROGRESS)
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            ->pluck('term_id');

        $set = [];
        foreach ($rows as $termId) {
            $set[(string) $termId] = true;
        }

        return $set;
    }

    public function owedCount(UserId $userId, DateTimeImmutable $now): int
    {
        return $this->owed($userId, $now)->count();
    }

    public function owedCardCount(UserId $userId, DateTimeImmutable $now): int
    {
        // Grouped rather than fetched: the chain length depends only on `acquisition` and
        // `learning_step`, so the whole answer is a handful of rows however large the backlog is.
        // A pair that is not `learning` owes one card whatever its step column happens to hold.
        $rows = $this->owed($userId, $now)
            ->selectRaw('acquisition, learning_step, count(*) as n')
            ->groupBy('acquisition', 'learning_step')
            ->get();

        $cards = 0;
        foreach ($rows as $row) {
            $n = (int) $row->n;
            $cards += $n * ((string) $row->acquisition === Acquisition::Learning->value
                ? LearningLadder::chainLength(
                    LearningLadder::clampRecognitionStep((int) $row->learning_step),
                )
                : 1);
        }

        return $cards;
    }

    /**
     * The owed population, as a query both counters run over.
     *
     * Mirrors EloquentDueTermsReader::selectableInPool()'s predicate, term for term: unfinished on
     * the ladder, or graduated and owed a review, or a `known` claim whose check came due. Written
     * once because two counters reading the same population by two predicates is how «повторить N»
     * and «~K карточек» would come to describe different days.
     */
    private function owed(UserId $userId, DateTimeImmutable $now): Builder
    {
        $bound = UtcInstant::bind($now);

        return DB::table(self::PROGRESS)
            ->where('user_id', $userId->value)
            ->where(static function (BuilderContract $q) use ($bound): void {
                $q->where(static function (BuilderContract $q) use ($bound): void {
                    $q->whereNotNull('enrolled_at')
                        ->where(static function (BuilderContract $q) use ($bound): void {
                            $q->where('acquisition', Acquisition::Learning->value)
                                ->orWhere(static function (BuilderContract $q) use ($bound): void {
                                    $q->where('acquisition', Acquisition::Graduated->value)
                                        ->where(static fn (BuilderContract $q) => $q
                                            ->whereNull('due_at')
                                            ->orWhere('due_at', '<=', $bound));
                                });
                        });
                })->orWhere(static function (BuilderContract $q) use ($bound): void {
                    $q->where('state', LearningState::Known->value)
                        ->whereNotNull('due_at')
                        ->where('due_at', '<=', $bound);
                });
            });
    }

    public function poolSize(UserId $userId): int
    {
        return $this->pool($userId)->count();
    }

    public function waitingInPool(UserId $userId): int
    {
        return $this->pool($userId)->where('acquisition', Acquisition::New->value)->count();
    }

    public function edgeTerms(UserId $userId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        // The learner's OWN words only — a `known` verification riding beside the pool is the system
        // auditing a claim, not a word slipping away, and it has no place under this heading.
        $rows = $this->pool($userId)
            ->where('due_at', '>', UtcInstant::bind($from))
            ->where('due_at', '<', UtcInstant::bind($until))
            ->orderBy('due_at')
            ->orderBy('term_id')
            ->limit($limit)
            ->get(['term_id', 'due_at']);

        return array_values($rows->map(static fn (object $r): ScheduledTermFact => new ScheduledTermFact(
            (string) $r->term_id,
            new DateTimeImmutable((string) $r->due_at),
        ))->all());
    }

    public function dueTomorrowCount(UserId $userId, DateTimeImmutable $tomorrowStart, DateTimeZone $tz): int
    {
        // Half-open on the day after, so «завтра» is the whole calendar day whatever time of day the
        // due dates in it happen to carry.
        return $this->pool($userId)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', UtcInstant::bind($tomorrowStart))
            ->where('due_at', '<', UtcInstant::bind($tomorrowStart->setTimezone($tz)->modify('+1 day')))
            ->count();
    }

    public function promotionsToday(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz): array
    {
        // WHERE THE DAY BEGAN, per pair: the rung the first card dealt today was dealt at. DISTINCT ON
        // over the day's own answers, ordered by the moment and then by the review id — two cards
        // answered inside the same millisecond still have to resolve to one row, and the ULID is the
        // only tiebreak that is stable across two runs of this query.
        $firstToday = $this->studyAnswersToday($userId, $now, $tz)
            ->whereNotNull('ladder_step')
            ->orderBy('term_id')
            ->orderBy('answered_at')
            ->orderBy('id')
            ->selectRaw('DISTINCT ON (term_id) term_id, ladder_step');

        $rows = DB::query()
            ->fromSub($firstToday, 'started')
            ->join(self::PROGRESS . ' as p', static function (JoinClause $join) use ($userId): void {
                $join->on('p.term_id', '=', 'started.term_id')->where('p.user_id', '=', $userId->value);
            })
            ->get(['started.term_id', 'started.ladder_step', 'p.acquisition', 'p.learning_step', 'p.successful_reviews', 'p.state']);

        $promotions = [];
        foreach ($rows as $row) {
            $from = (int) $row->ladder_step;
            // The ladder's ONE derivation, not a second expression over the same columns. `known` is
            // outside the ladder and returns null — a pair whose rung cannot be named cannot be said
            // to have risen, so it is simply not a promotion.
            $to = LearningLadder::stepFor(
                Acquisition::tryFrom((string) $row->acquisition) ?? Acquisition::Graduated,
                (int) $row->successful_reviews,
                (int) $row->learning_step,
                isKnown: (string) $row->state === LearningState::Known->value,
            );

            if ($to !== null && $to > $from) {
                $promotions[] = new TermPromotionFact((string) $row->term_id, $from, $to);
            }
        }

        return $promotions;
    }

    public function graduatedSince(UserId $userId, DateTimeImmutable $since): int
    {
        $bound = UtcInstant::bind($since);

        // A pair's graduation day is the day of its FIRST card above the recognition rungs. So: pairs
        // that have one inside the window and none before it. Counted over the log rather than over
        // the progress row, which carries no graduation date and cannot be made to carry one without
        // a migration this number does not deserve.
        return DB::table('reviews')
            ->where('user_id', $userId->value)
            ->where('is_practice', false)
            ->where('answered_at', '>=', $bound)
            ->where(static fn (BuilderContract $q) => $q
                ->whereNull('reviews.ladder_step')
                ->orWhere('reviews.ladder_step', '>=', LearningLadder::STEP_ASSEMBLY))
            ->whereNotExists(static function (Builder $q) use ($userId, $bound): void {
                $q->from('reviews as earlier')
                    ->whereColumn('earlier.term_id', 'reviews.term_id')
                    ->where('earlier.user_id', $userId->value)
                    ->where('earlier.is_practice', false)
                    ->where('earlier.answered_at', '<', $bound)
                    ->where(static fn (BuilderContract $q) => $q
                        ->whereNull('earlier.ladder_step')
                        ->orWhere('earlier.ladder_step', '>=', LearningLadder::STEP_ASSEMBLY));
            })
            ->distinct()
            ->count('reviews.term_id');
    }

    public function nextReview(UserId $userId, DateTimeImmutable $from, DateTimeZone $tz): ?HomeNextReviewView
    {
        // Everything that will actually be DEALT on that day, so the count matches the session the
        // learner will get: the pool, plus a `known` verification whose check comes due
        // (EloquentDueTermsReader deals both).
        $row = DB::table(self::PROGRESS)
            ->where('user_id', $userId->value)
            ->where(static fn (BuilderContract $q) => $q
                ->whereNotNull('enrolled_at')
                ->orWhere('state', LearningState::Known->value))
            ->whereNotNull('due_at')
            // INCLUSIVE: a day-scale due date sits at the owner's local midnight, so a repeat due
            // «tomorrow» IS tomorrow's midnight — a strict `>` here silently skipped the whole first
            // scheduled day and reported the one after it.
            ->where('due_at', '>=', UtcInstant::bind($from))
            ->selectRaw('(due_at AT TIME ZONE ?)::date AS d, count(*) AS n', [$tz->getName()])
            // GROUP BY the select's ORDINAL, not a repeat of the expression: written out again it
            // would carry its own placeholder, and Postgres compares `(due_at AT TIME ZONE $1)` to
            // `(due_at AT TIME ZONE $2)` as two different expressions — "must appear in the GROUP BY
            // clause" on an expression that plainly does.
            ->groupByRaw('1')
            ->orderBy('d')
            ->first();

        if ($row === null) {
            return null;
        }

        return new HomeNextReviewView(
            (new DateTimeImmutable((string) $row->d))->format('Y-m-d'),
            (int) $row->n,
        );
    }

    public function todayAnswers(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz): HomeTodayView
    {
        $row = $this->studyAnswersToday($userId, $now, $tz)
            ->selectRaw('count(*) AS n, count(DISTINCT term_id) AS w, coalesce(sum(latency_ms), 0) AS ms')
            ->first();

        return new HomeTodayView(
            (int) ($row->n ?? 0),
            intdiv((int) ($row->ms ?? 0), 1000),
            (int) ($row->w ?? 0),
        );
    }

    public function hardestToday(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $rows = $this->studyAnswersToday($userId, $now, $tz)
            ->where('is_correct', false)
            ->groupBy('term_id')
            ->selectRaw('term_id, count(*) AS errors')
            ->orderByDesc('errors')
            ->orderBy('term_id')
            ->limit($limit)
            ->get();

        return array_values($rows->map(static fn (object $r): TermErrorFact => new TermErrorFact(
            (string) $r->term_id,
            (int) $r->errors,
        ))->all());
    }

    public function averageCardSeconds(UserId $userId, int $sampleSize): ?int
    {
        $recent = DB::table('reviews')
            ->where('user_id', $userId->value)
            ->where('is_practice', false)
            ->whereNotNull('latency_ms')
            ->orderByDesc('answered_at')
            ->limit(max(1, $sampleSize))
            ->select(['latency_ms']);

        return $this->winsorisedSeconds($recent);
    }

    /**
     * The winsorised mean of a latency column, in whole seconds — or null below {@see MIN_SAMPLES}.
     *
     * One implementation for answers and for swipes: the two differ in which log they read, never in
     * how the average is taken, and a second copy of the ceiling and the clamp is a second place for
     * them to drift.
     *
     * @param  \Illuminate\Database\Query\Builder  $recent  a sub-select of `latency_ms`
     */
    private function winsorisedSeconds(Builder $recent): ?int
    {
        /** @var object{n: int|string, ms: float|string|null}|null $row */
        $row = DB::query()
            ->fromSub($recent, 'recent')
            ->selectRaw('count(*) AS n, avg(least(latency_ms, ?)) AS ms', [self::LATENCY_CEILING_MS])
            ->first();

        if ($row === null || $row->ms === null || (int) $row->n < self::MIN_SAMPLES) {
            return null;
        }

        $seconds = (int) round(((float) $row->ms) / 1000);

        return max(self::MIN_CARD_SECONDS, min(self::MAX_CARD_SECONDS, $seconds));
    }

    public function averageSwipeSeconds(UserId $userId, int $sampleSize): ?int
    {
        $recent = DB::table('term_triages')
            ->where('user_id', $userId->value)
            ->whereNotNull('latency_ms')
            ->orderByDesc('decided_at')
            ->limit(max(1, $sampleSize))
            ->select(['latency_ms']);

        return $this->winsorisedSeconds($recent);
    }

    public function lastTouchedAt(UserId $userId, array $termIds): ?DateTimeImmutable
    {
        if ($termIds === []) {
            return null;
        }

        $answered = DB::table('reviews')
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            ->max('answered_at');

        $swiped = DB::table('term_triages')
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            ->max('decided_at');

        $moments = array_values(array_filter(
            [$answered, $swiped],
            static fn ($v): bool => is_string($v) && $v !== '',
        ));

        if ($moments === []) {
            return null;
        }

        $latest = null;
        foreach ($moments as $moment) {
            $at = new DateTimeImmutable($moment);
            if ($latest === null || $at > $latest) {
                $latest = $at;
            }
        }

        return $latest;
    }

    /** One learner's pool: enrolled pairs, the population every «в работе» number is about. */
    private function pool(UserId $userId): Builder
    {
        return DB::table(self::PROGRESS)
            ->where('user_id', $userId->value)
            ->whereNotNull('enrolled_at');
    }

    /**
     * Today's STUDY answers, in the learner's calendar day. Practice is excluded on purpose: it is
     * activity, it keeps the streak, and it is not the day's plan.
     */
    private function studyAnswersToday(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz): Builder
    {
        return DB::table('reviews')
            ->where('user_id', $userId->value)
            ->where('is_practice', false)
            ->whereRaw('(answered_at AT TIME ZONE ?)::date = ?', [
                $tz->getName(),
                $now->setTimezone($tz)->format('Y-m-d'),
            ]);
    }
}
