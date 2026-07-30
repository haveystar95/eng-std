<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * How a card is presented. The mode changes how an answer is graded, never how it is
 * scheduled — the scheduler only ever sees a {@see Grade}. A recognition mode (pick from
 * options, assemble from given chips) proves far less than free recall, so it can never award
 * `easy`: a four-way guess sent to a month-long interval is a word quietly forgotten.
 */
enum ExerciseMode: string
{
    case MultipleChoice = 'multiple_choice';
    case WordBank = 'word_bank';
    case Typing = 'typing';
    case Listening = 'listening';
    case Cloze = 'cloze';

    /** The best grade this mode is allowed to produce. Recognition caps at `good`. */
    public function maxGrade(): Grade
    {
        return match ($this) {
            self::Typing, self::Listening => Grade::Easy,
            self::MultipleChoice, self::WordBank, self::Cloze => Grade::Good,
        };
    }

    /** A production mode reproduces from memory, so a fast clean answer can earn `easy`. */
    public function isProduction(): bool
    {
        return $this->maxGrade() === Grade::Easy;
    }
}
