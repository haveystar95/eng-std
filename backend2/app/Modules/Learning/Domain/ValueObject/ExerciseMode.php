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
    case Scramble = 'scramble';
    case Dictation = 'dictation';

    /** The best grade this mode is allowed to produce. Recognition caps at `good`. */
    public function maxGrade(): Grade
    {
        return match ($this) {
            // `dictation` writes a whole sentence from hearing it alone — nothing is on the screen
            // to lean on, so it is production in the fullest sense the app has.
            self::Typing, self::Listening, self::Dictation => Grade::Easy,
            // `scramble` hands over every word of the sentence — assembling given tiles is
            // recognition, exactly like word_bank, so it can never buy a month-long interval.
            self::MultipleChoice, self::WordBank, self::Cloze, self::Scramble => Grade::Good,
        };
    }

    /**
     * Is this mode's expected answer the term's EXAMPLE SENTENCE rather than the term itself?
     *
     * The answer key stays what it always was — the term's own target-language forms — but a
     * sentence-level exercise asks a different question, so it is graded against the sentence it
     * showed. This is the one place that distinction is made; the grader and the client card
     * builder both read it, so they cannot disagree about what "correct" means.
     */
    public function gradesAgainstExample(): bool
    {
        return match ($this) {
            self::Scramble, self::Dictation => true,
            self::MultipleChoice, self::WordBank, self::Typing, self::Listening, self::Cloze => false,
        };
    }

    /** A production mode reproduces from memory, so a fast clean answer can earn `easy`. */
    public function isProduction(): bool
    {
        return $this->maxGrade() === Grade::Easy;
    }
}
