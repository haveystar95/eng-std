<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * What a given term's DATA allows it to be drilled in — "can this exercise even be built for this
 * term", in one place.
 *
 * This is deliberately separate from the ladder. The ladder answers *which* exercise comes next
 * (a function of how often the term has been answered); this answers *which are possible at all*
 * (a function of the term's content). Keeping them apart is what lets a ladder be a plain ordered
 * list — `[base, listening, cloze]` filtered through {@see only()} — instead of a chain of
 * ternaries over term shape, which is how it was written before and how it would have become
 * unreadable at five modes.
 *
 * Every applicability rule lives in {@see supports()} and nowhere else: the SRS ladder, the free-
 * practice fan and the client's port all ask this one question. A new mode adds one arm to the
 * match, and PHPStan's exhaustiveness check points at it.
 */
final readonly class TermPlayability
{
    /** Nothing to assemble from a single word — word_bank would be a one-chip card. */
    public const MIN_WORD_BANK_WORDS = 2;

    /**
     * Scramble's sentence-length window. Below the floor there is nothing to assemble (3 chips is
     * six possible orders); above the ceiling the screen fills with tiles and one misplaced word
     * costs the whole card. 4…12 covers 96% of the examples in the live corpus.
     */
    public const MIN_SCRAMBLE_TOKENS = 4;
    public const MAX_SCRAMBLE_TOKENS = 12;

    /**
     * Dictation's window, tighter at the top than scramble's on purpose: typing a sentence out by
     * ear costs far more than dropping tiles into place, and a twelve-word sentence typed blind is
     * a chore rather than a drill. The floor is the same — below four words there is no sentence
     * to hear, only the term, which is what `listening` already asks for.
     */
    public const MIN_DICTATION_TOKENS = 4;
    public const MAX_DICTATION_TOKENS = 10;

    /**
     * `pick_correct` shows the right sentence plus two wrong ones. Two is therefore not a preference
     * but the card's floor: with one wrong option the choice is between two sentences and a coin toss
     * scores 50%, which teaches nothing and schedules a month.
     *
     * The enrichment станок writes these, and the review deletes the ones that turned out to be
     * grammatical — so a term's eligibility genuinely comes and goes with its CONTENT, which is
     * exactly what this class is for.
     */
    public const MIN_PICK_CORRECT_DISTRACTORS = 2;

    /**
     * @param  int   $answerWordCount        whitespace-separated words in the target answer
     * @param  bool  $clozeable              the term's example exists and contains the answer, so a
     *                                       blank can be cut from it
     * @param  int   $exampleTokenCount      chips the pinned example yields ({@see SentenceTokenizer})
     * @param  bool  $hasExampleTranslation  the example is translated — scramble's prompt IS that
     *                                       translation ("assemble this in English"), so without it
     *                                       the card has no question
     * @param  bool  $exampleIsAnswer        the example tokenizes to the term itself, so scrambling
     *                                       it would deal the same tiles word_bank already deals
     * @param  int   $distractorCount        validated wrong versions of the pinned example that the
     *                                       enrichment станок wrote — pick_correct's options
     */
    public function __construct(
        public int $answerWordCount,
        public bool $clozeable,
        public int $exampleTokenCount = 0,
        public bool $hasExampleTranslation = false,
        public bool $exampleIsAnswer = false,
        public int $distractorCount = 0,
    ) {}

    /** Can this term be drilled in this mode at all? The ONE place applicability is decided. */
    public function supports(ExerciseMode $mode): bool
    {
        return match ($mode) {
            ExerciseMode::WordBank => $this->answerWordCount >= self::MIN_WORD_BANK_WORDS,
            ExerciseMode::Cloze => $this->clozeable,
            ExerciseMode::Scramble => ! $this->exampleIsAnswer
                && $this->hasExampleTranslation
                && $this->exampleTokenCount >= self::MIN_SCRAMBLE_TOKENS
                && $this->exampleTokenCount <= self::MAX_SCRAMBLE_TOKENS,
            // No translation needed: the task IS the audio, so the card has no written prompt to
            // put one in. An example that is merely the term is excluded for the same reason as in
            // scramble — hearing the term and typing it is `listening`, which already exists.
            ExerciseMode::Dictation => ! $this->exampleIsAnswer
                && $this->exampleTokenCount >= self::MIN_DICTATION_TOKENS
                && $this->exampleTokenCount <= self::MAX_DICTATION_TOKENS,
            // The prompt is the example's translation ("which of these is right?"), so the card needs
            // it, and it needs two wrong sentences to choose among. No length window: the learner
            // reads three sentences instead of assembling one, so a long example costs attention
            // rather than becoming unplayable.
            ExerciseMode::PickCorrect => ! $this->exampleIsAnswer
                && $this->hasExampleTranslation
                && $this->distractorCount >= self::MIN_PICK_CORRECT_DISTRACTORS,
            // multiple_choice / typing / listening fit any term — they ask for the term itself.
            ExerciseMode::MultipleChoice, ExerciseMode::Typing, ExerciseMode::Listening => true,
        };
    }

    /**
     * The given modes this term can actually be drilled in, order preserved. A ladder passes
     * through here instead of branching on term shape.
     *
     * @param  list<ExerciseMode>  $modes
     * @return list<ExerciseMode>
     */
    public function only(array $modes): array
    {
        return array_values(array_filter($modes, $this->supports(...)));
    }
}
