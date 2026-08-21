<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Service\LearningLadder;

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
     * DESCRIPTION → WORD: the card shows what a word MEANS, in the language being learned, and asks
     * which of four words it is describing.
     *
     * The one recognition card in the app whose prompt is neither the term nor its translation, and
     * that is the whole point of it. Every other trainer asks the learner to cross the bridge
     * between two languages; this one asks a question entirely inside English and answers it in
     * English — which is what "knowing a word" eventually has to mean, and what a translation pair
     * can never test. It is also the only card that can distinguish two words the learner has
     * collapsed onto one Russian gloss, because the description separates them and the gloss does
     * not.
     *
     * What it needs is the one piece of content nothing else in this app uses: a DESCRIPTION
     * ({@see \App\Modules\Learning\Domain\ValueObject\ContentGap::NoDescription}). A term without
     * one is refused rather than degraded — there is nothing to degrade to, since the prompt IS the
     * description.
     *
     * Its options are OTHER WORDS from the learner's own pool, so like `multiple_choice` it is
     * pool-dependent and its ceiling is `good`: a one-in-four tap is the weakest evidence the app
     * collects, and sending it to a month-long interval is a word quietly forgotten.
     */
    case DescriptionMatch = 'description_match';

    /**
     * SPEAKING RECALL: the learner reads the card and SAYS the answer out loud; the device
     * recognises the speech on-device and uploads the recognised text as the answer.
     *
     * The frame matters and is worth stating here, because everything below follows from it: this
     * checks that the word can be RETRIEVED and PRODUCED, not that it is pronounced well. There is
     * no accent scoring anywhere in this codebase and there must not be one — a recogniser's
     * disagreement is a fact about a microphone in a noisy room at least as often as it is a fact
     * about the learner.
     *
     * That frame is also why the CHANNEL is forgiven and the KNOWLEDGE is not, which is the exact
     * inversion of {@see forgivesTypos()}. A card the recogniser could not hear is retried on the
     * device and, after a few attempts, SKIPPED — and a skip uploads nothing at all, so no review
     * row is written and the pair comes back on its own schedule. Only the learner pressing «не
     * помню» sends an answer, and that one is an honest lapse like any other. The server therefore
     * never has to tell "silence" from "forgotten": it is only ever handed the second one.
     */
    case Speaking = 'speaking';

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
            //
            // `speaking` is capped here even though its early form IS free recall, for two reasons
            // that point the same way. Its late form is READING THE EXAMPLE ALOUD — reinforcement,
            // not retrieval — and one mode cannot hold two ceilings without threading the rung
            // through a second predicate. And `easy` is bought by SPEED, which on this card is the
            // recogniser's as much as the learner's: the tap, the utterance and the recogniser's
            // own settling time all land in the same latency. Capping is the conservative
            // direction — the rule forbids being more generous than the evidence, never less.
            //
            // `description_match` is a four-way tap like multiple_choice, and caps for exactly the
            // same reason: the learner recognised a word among four, which is not evidence that
            // they could produce it.
            self::MultipleChoice, self::WordBank, self::Cloze, self::Scramble, self::PickCorrect,
            self::Speaking, self::DescriptionMatch => Grade::Good,
        };
    }

    /**
     * Is this mode's expected answer the term's EXAMPLE SENTENCE rather than the term itself?
     *
     * The answer key stays what it always was — the term's own target-language forms — but a
     * sentence-level exercise asks a different question, so it is graded against the sentence it
     * showed. This is the one place that distinction is made; the grader and the client card
     * builder both read it, so they cannot disagree about what "correct" means.
     *
     * `speaking` is the one mode whose question CHANGES WITH THE RUNG, which is why the rung is a
     * required argument rather than an optional one — every call site has to answer it, and
     * forgetting cannot silently pick a form. Early on it asks for the WORD (translation + photo on
     * screen, say the term: free recall out loud); from the dictation rung it asks the learner to
     * READ THE PINNED EXAMPLE ALOUD, which is reinforcement rather than retrieval. It is one mode
     * rather than two because the difference is what the same trainer asks at a later point on the
     * ladder — exactly like the two directions of the recognition card, which are also one mode
     * read off the rung and not two entries in the registry.
     *
     * A null rung means the ladder does not apply (free practice, a `known` verification), and
     * there the word form is the honest reading: practice drills the term, and nothing off the
     * ladder has earned the later form.
     *
     * Content is NOT considered here — this answers what the mode ASKS, not what the term can
     * supply. A speaking card on a term with no example degrades to the word form, and that
     * degradation belongs at the call sites, which already hold the content and already guard it
     * the same way for scramble and dictation.
     */
    public function gradesAgainstExample(?int $ladderStep): bool
    {
        return match ($this) {
            self::Intro => throw new \LogicException('intro is never graded, so nothing is graded against.'),
            // `pick_correct` shows three sentences and asks which one is right, so the answer the
            // learner commits IS a sentence — the pinned example, verbatim. Grading it against the
            // term's forms would fail every correct pick.
            self::Scramble, self::Dictation, self::PickCorrect => true,
            self::Speaking => $ladderStep !== null && $ladderStep >= LearningLadder::STEP_DICTATION,
            // The description IS the question, and the answer is the TERM — the example is not on
            // this card at all.
            self::MultipleChoice, self::WordBank, self::Typing, self::Listening, self::Cloze,
            self::DescriptionMatch => false,
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
            // Speech has no typos to forgive: a recogniser returns whole words, never a slipped
            // key, so a one-character difference means it heard a DIFFERENT word — and forgiving
            // that would mark «bare» correct for «bear». What gets forgiven on this trainer is the
            // CHANNEL, and that is forgiven on the device, by retrying and then skipping without
            // uploading anything. See the case docblock: the leniency is inverted, not removed.
            self::MultipleChoice, self::WordBank, self::Scramble, self::PickCorrect, self::Speaking,
            // Tapped, so there is no typing to forgive — and the four options are whole different
            // words, never one character apart.
            self::DescriptionMatch => false,
            self::Intro => throw new \LogicException('intro accepts no answer, so there is no typo to forgive.'),
        };
    }
}
