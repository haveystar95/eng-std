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
    case PickCorrect = 'pick_correct';

    /**
     * The zeroth rung: the word is SHOWN, not asked. Term, translation, transcription, example —
     * the learner reads it and moves on.
     *
     * It is a mode rather than a session type or a flag because that is what makes it toggleable
     * like every other trainer (it appears in the «Тренажёры» registry on its own) and what keeps
     * "which card next" a single question with a single answer. What makes it different is one
     * thing only, {@see isGraded()}: it produces no answer, so no {@see Grade}, and NOTHING is
     * written to `reviews` — the review log holds real retrievals, and an intro asks for nothing.
     * What it writes instead is a `term_exposures` row.
     */
    case Intro = 'intro';

    /**
     * Does this mode produce an answer to grade at all?
     *
     * Everything downstream of an answer — the grader, the latency median, the scheduler, the
     * review log — is unreachable for `intro`, and the single guard that keeps it that way is this
     * predicate. Note that `maxGrade()` and friends deliberately THROW for `intro` rather than
     * inventing a neutral value: a grading path that quietly accepts an intro would fabricate
     * retrievals that never happened.
     */
    public function isGraded(): bool
    {
        return $this !== self::Intro;
    }

    /** The best grade this mode is allowed to produce. Recognition caps at `good`. */
    public function maxGrade(): Grade
    {
        return match ($this) {
            self::Intro => throw new \LogicException('intro produces no answer, so it has no grade.'),
            // `dictation` writes a whole sentence from hearing it alone — nothing is on the screen
            // to lean on, so it is production in the fullest sense the app has.
            self::Typing, self::Listening, self::Dictation => Grade::Easy,
            // `scramble` hands over every word of the sentence — assembling given tiles is
            // recognition, exactly like word_bank, so it can never buy a month-long interval.
            // `pick_correct` is a three-way choice: the weakest evidence the app collects.
            self::MultipleChoice, self::WordBank, self::Cloze, self::Scramble, self::PickCorrect => Grade::Good,
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
            self::Intro => throw new \LogicException('intro is never graded, so nothing is graded against.'),
            // `pick_correct` shows three sentences and asks which one is right, so the answer the
            // learner commits IS a sentence — the pinned example, verbatim. Grading it against the
            // term's forms would fail every correct pick.
            self::Scramble, self::Dictation, self::PickCorrect => true,
            self::MultipleChoice, self::WordBank, self::Typing, self::Listening, self::Cloze => false,
        };
    }

    /** A production mode reproduces from memory, so a fast clean answer can earn `easy`. */
    public function isProduction(): bool
    {
        return $this->maxGrade() === Grade::Easy;
    }

    /**
     * Does a single-character difference deserve forgiveness on this mode?
     *
     * Typo leniency exists to forgive TYPING — a slipped key on a long word should not wipe a
     * month-long interval. When the answer is TAPPED or ASSEMBLED from material we handed over, there
     * is no typing to forgive, and a one-character difference is very often the exact thing being
     * tested: `pick_correct` offers "Could you takes a photo…" against "Could you take a photo…",
     * which is one character apart and is the whole point of the card. Forgiving it there marks the
     * broken sentence correct and schedules the term as learned.
     *
     * So: only the typed modes forgive. For word_bank and scramble the learner can only place tiles
     * we dealt, so the path was unreachable anyway — stating it here keeps it that way.
     */
    public function forgivesTypos(): bool
    {
        return match ($this) {
            self::Typing, self::Listening, self::Cloze, self::Dictation => true,
            self::MultipleChoice, self::WordBank, self::Scramble, self::PickCorrect => false,
            self::Intro => throw new \LogicException('intro accepts no answer, so there is no typo to forgive.'),
        };
    }
}
