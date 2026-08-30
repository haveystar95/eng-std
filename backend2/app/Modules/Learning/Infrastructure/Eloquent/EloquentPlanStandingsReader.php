<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\PlanStandingsReader;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanStageFact;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * The review log, read as stage evidence.
 *
 * Ordered by `client_seq` and then `answered_at`, which is the project's standing ordering rule for
 * this log and not a preference: device clocks disagree, and a batch that arrives out of order must
 * fold the same way whatever order it arrives in. The stage ladder is order-sensitive — «three
 * misses IN A ROW», «the fact that closed the last step» — so reading this log in receipt order
 * would produce a different stage for the same history.
 *
 * The local day is computed in SQL, in the learner's own timezone, for the same reason the due-date
 * migration moved to owner-local midnight: «после ночи» is a fact about a calendar, and an answer at
 * 23:50 and one at 00:10 are two days apart to a person.
 */
final class EloquentPlanStandingsReader implements PlanStandingsReader
{
    public function factsFor(UserId $user, array $termIds, DateTimeZone $tz): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('reviews')
            ->where('user_id', $user->value)
            ->whereIn('term_id', $termIds)
            ->where('is_practice', false)
            ->orderBy('client_seq')
            ->orderBy('answered_at')
            ->orderBy('id')
            ->get(['term_id', 'exercise_mode', 'is_correct', 'answered_at']);

        $zone = $tz->getName();
        $out = [];
        foreach ($rows as $row) {
            $mode = ExerciseMode::tryFrom((string) $row->exercise_mode);
            // A mode this build does not know — a row written by a newer deploy, read after a
            // rollback. Skipped rather than fatal, exactly as the mode settings reader does: the
            // learner's stage is one step behind, not unreadable.
            if ($mode === null) {
                continue;
            }

            $out[(string) $row->term_id][] = new PlanStageFact(
                mode: $mode,
                correct: (bool) $row->is_correct,
                localDate: $this->localDate((string) $row->answered_at, $zone),
            );
        }

        return $out;
    }

    public function introducedAmong(UserId $user, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $out = [];
        foreach (
            DB::table('term_exposures')
                ->where('user_id', $user->value)
                ->whereIn('term_id', $termIds)
                ->pluck('term_id') as $termId
        ) {
            $out[(string) $termId] = true;
        }

        return $out;
    }

    /**
     * The instant, as a day on the learner's own calendar.
     *
     * Done in PHP rather than in the query because the alternative — `AT TIME ZONE` inside a
     * `SELECT` — would make the answer depend on Postgres's timezone catalogue rather than on the
     * same `DateTimeZone` every other date decision in this module is made with, and the two do
     * disagree on historical offsets.
     */
    private function localDate(string $answeredAt, string $zone): string
    {
        return (new \DateTimeImmutable($answeredAt))
            ->setTimezone(new DateTimeZone($zone))
            ->format('Y-m-d');
    }
}
