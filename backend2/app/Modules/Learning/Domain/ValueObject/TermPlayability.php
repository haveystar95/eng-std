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
     * @param  int   $answerWordCount  whitespace-separated words in the target answer
     * @param  bool  $clozeable        the term's example exists and contains the answer, so a blank
     *                                 can be cut from it
     */
    public function __construct(
        public int $answerWordCount,
        public bool $clozeable,
    ) {}

    /** Can this term be drilled in this mode at all? The ONE place applicability is decided. */
    public function supports(ExerciseMode $mode): bool
    {
        return match ($mode) {
            ExerciseMode::WordBank => $this->answerWordCount >= self::MIN_WORD_BANK_WORDS,
            ExerciseMode::Cloze => $this->clozeable,
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
