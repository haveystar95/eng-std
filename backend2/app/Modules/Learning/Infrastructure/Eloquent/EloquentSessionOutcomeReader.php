<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\SessionOutcomeReader;
use App\Modules\Learning\Domain\ValueObject\SessionOutcome;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes a run's summary from the two append-only logs it wrote: `reviews` for what was
 * answered, `term_exposures` for what was merely shown.
 *
 * `is_correct` is read as the column rather than re-derived from the grade — the column IS the
 * definition the learner was shown, and re-deriving it here would be a third copy of a rule that
 * has already been two copies once (QA-11).
 */
final class EloquentSessionOutcomeReader implements SessionOutcomeReader
{
    public function forSession(StudySessionId $sessionId): SessionOutcome
    {
        /** @var object{cards: int|string, correct: int|string|null}|null $answers */
        $answers = DB::table('reviews')
            ->selectRaw('count(*) as cards, count(*) filter (where is_correct) as correct')
            ->where('session_id', $sessionId->value)
            ->first();

        $intros = DB::table('term_exposures')->where('session_id', $sessionId->value)->count();

        return new SessionOutcome(
            cards: (int) ($answers->cards ?? 0),
            correct: (int) ($answers->correct ?? 0),
            intros: $intros,
        );
    }
}
