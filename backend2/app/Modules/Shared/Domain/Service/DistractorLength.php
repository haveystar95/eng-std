<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * HOW LONG A WRONG ANSWER MAY BE — the second half of «что может стоять рядом с ответом».
 *
 * {@see DistractorFamily} settles the SHAPE: a word beside a word, a question beside questions.
 * Inside one shape, length still gives the answer away. Asked to recognise `key` and offered
 * `accommodation`, `neighbourhood` and `responsibility`, a learner picks the short one and never
 * reads it — the same defect the family rule exists to prevent, one level finer. It got worse the
 * day the option pool became the whole catalogue: a four-letter target could suddenly be offered
 * anything the language has.
 *
 * ## Two measures, because a phrase is not a long word
 *
 * `word`, `chunk` and «no kind» are measured in CHARACTERS: they are one lexical item, and its
 * visual weight on the card is its spelling. A `line` is measured in WORDS, because it is a turn in
 * a conversation and what the eye compares is how many words it holds — «Yes.» against a
 * fourteen-word sentence is obvious at a glance whether or not the character counts happen to be
 * close.
 *
 * Tolerances are ±, relative to the TARGET, and they are configuration
 * (`config/learning.php → distractor_length`) rather than constants: they are a product judgement
 * about how hard a card should be, and the first time one of them is wrong it should move without a
 * deploy of the domain.
 *
 * ## What happens when nothing fits
 *
 * Nothing is substituted. The band is a refusal, not a preference: a candidate outside it is not a
 * worse option, it is an option that answers the card by its shape. A choice card that cannot be
 * filled is DROPPED — and the same rule is read by {@see \App\Modules\Learning\Application\Service\PlanStandings},
 * so a checklist never owes a card the pool cannot furnish, and by
 * {@see \App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentDistractorReader}, which builds it.
 * The counter is `plan_distractor_starved`.
 */
final readonly class DistractorLength
{
    /** `word`, `chunk`, «no kind» — ± this share of the target's characters. */
    public const DEFAULT_CHAR_TOLERANCE = 0.5;

    /** `line` — ± this share of the target's word count. */
    public const DEFAULT_WORD_TOLERANCE = 0.4;

    private const LINE = 'line';

    public function __construct(
        private float $charTolerance = self::DEFAULT_CHAR_TOLERANCE,
        private float $wordTolerance = self::DEFAULT_WORD_TOLERANCE,
    ) {}

    /**
     * May `$candidate` stand beside `$target` on the same card?
     *
     * `$kind` is the TARGET's — the band is measured in the target's own units, and the family rule
     * has already guaranteed the candidate is of the same kind by the time this is asked.
     */
    public function fits(?string $kind, string $target, string $candidate): bool
    {
        [$measure, $tolerance] = $kind === self::LINE
            ? ['words', $this->wordTolerance]
            : ['chars', $this->charTolerance];

        $want = $measure === 'words' ? self::words($target) : self::chars($target);
        $have = $measure === 'words' ? self::words($candidate) : self::chars($candidate);

        // A target that measures zero has no band to speak of — nothing is «±50 % of nothing», and
        // refusing everything here would drop cards over a data defect rather than over a length.
        if ($want === 0) {
            return true;
        }

        return abs($have - $want) <= $want * $tolerance;
    }

    private static function chars(string $text): int
    {
        return mb_strlen(trim($text));
    }

    private static function words(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 0 : count($words);
    }
}
