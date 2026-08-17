<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * What a finished study run amounted to — the compact summary stored on `study_sessions.stats`.
 *
 * Every number here is DERIVED from the append-only logs the run produced (its reviews and its
 * exposures), never taken from the client. The client says WHEN it finished; what happened is
 * something the server already knows, and a summary that could disagree with the log it summarises
 * would be worse than no summary at all.
 *
 * «Correct» is the row's own `is_correct` — anything but `again` — the same definition the learner
 * saw and the daily projection counts. See {@see Grade::isConfidentRecall()} for the other, stricter
 * question and why the two are kept apart.
 *
 * Intros are counted separately and are not cards: an intro is shown, not asked, so it produces an
 * exposure and no review. Folding it into `cards` would make the summary disagree with the session
 * screen, which has always left intros out of its tally.
 */
final readonly class SessionOutcome
{
    public function __construct(
        public int $cards,
        public int $correct,
        public int $intros,
    ) {}

    /** @return array{cards: int, correct: int, intros: int} */
    public function toArray(): array
    {
        return ['cards' => $this->cards, 'correct' => $this->correct, 'intros' => $this->intros];
    }

    /** Did this run do anything at all? An abandoned session is left open rather than closed empty. */
    public function isEmpty(): bool
    {
        return $this->cards === 0 && $this->intros === 0;
    }
}
