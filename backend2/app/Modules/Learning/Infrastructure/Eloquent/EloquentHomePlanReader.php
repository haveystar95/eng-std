<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\HomeNextReviewView;
use App\Modules\Learning\Application\Dto\HomeTodayView;
use App\Modules\Learning\Application\Dto\ScheduledTermFact;
use App\Modules\Learning\Application\Dto\TermErrorFact;
use App\Modules\Learning\Application\Port\HomePlanReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
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
            ->selectRaw('count(*) AS n, coalesce(sum(latency_ms), 0) AS ms')
            ->first();

        return new HomeTodayView(
            (int) ($row->n ?? 0),
            intdiv((int) ($row->ms ?? 0), 1000),
        );
    }

    public function hardestOfLastSession(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $sessionId = $this->studyAnswersToday($userId, $now, $tz)
            ->whereNotNull('session_id')
            ->orderByDesc('answered_at')
            ->value('session_id');

        if ($sessionId === null) {
            return [];
        }

        $rows = DB::table('reviews')
            ->where('user_id', $userId->value)
            ->where('session_id', (string) $sessionId)
            ->where('is_practice', false)
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
