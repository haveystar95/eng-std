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
     * @param  bool  $hasDescription         the term has a description in the language being learned
     *                                       — the PROMPT of the description_match card, so without
     *                                       one there is no card rather than a lesser one
     */
    public function __construct(
        public int $answerWordCount,
        public bool $clozeable,
        public int $exampleTokenCount = 0,
        public bool $hasExampleTranslation = false,
        public bool $exampleIsAnswer = false,
        public int $distractorCount = 0,
        public bool $hasDescription = false,
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
            // The description IS the prompt, so this is the one gate with nothing to fall back on:
            // a card with no question is not a lesser card, it is not a card. Note this says
            // nothing about the OPTIONS — those are other pool words, which is a fact about the
            // session, not about this term (see ModeContentRequirements::isPoolDependent).
            ExerciseMode::DescriptionMatch => $this->hasDescription,
            // multiple_choice / typing / listening fit any term — they ask for the term itself.
            // `intro` asks for nothing at all, so there is no content it could lack: a term with no
            // example and no transcription still has a text and a translation to be shown.
            //
            // `speaking` fits every term for the same reason as typing: its WORD form asks for the
            // term, which every term has. Its late form asks for the example, but a term without
            // one simply keeps being asked for the word — a degradation inside the mode, never a
            // reason to make the whole trainer unavailable. This is deliberately NOT gated on the
            // example: gating here would silently drop speaking from every exampleless term at
            // every rung, including the early one where the example was never wanted.
            ExerciseMode::MultipleChoice, ExerciseMode::Typing, ExerciseMode::Listening,
            ExerciseMode::Speaking, ExerciseMode::Intro => true,
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
