<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/** How well the user recalled a term. Drives the scheduler; it is the only review input. */
enum Grade: string
{
    case Again = 'again';
    case Hard = 'hard';
    case Good = 'good';
    case Easy = 'easy';

    /**
     * Did the learner recall this WITHOUT HESITATION — `good` or `easy`, never `hard`?
     *
     * A strictly narrower question than «did they get it right», which is {@see Review::isCorrect()}
     * and is anything but `again`. Both are true statements about the same answer, and keeping them
     * apart is the point: this one is a SCHEDULING signal (memory strength, the input FSRS will
     * want), the other is what the learner did.
     *
     * It used to be called `isCorrect()`, and under that name the daily-stats projector counted the
     * day's «correct» answers with it — which read 4 correct out of 12 on a day the learner got 11
     * right (QA-11, приёмка 17.08). `hard` is handed out generously on the recognition rungs, where
     * it mostly measures the pause between two taps rather than shaky memory, so the number shown
     * back was a third of the truth. The projector now counts what the row itself says; this stays,
     * under a name that cannot be mistaken for it, for the scheduler.
     */
    public function isConfidentRecall(): bool
    {
        return $this === self::Good || $this === self::Easy;
    }
}
