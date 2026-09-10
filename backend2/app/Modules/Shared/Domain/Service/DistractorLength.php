<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * HOW LONG A WRONG ANSWER MAY BE — «что может стоять рядом с ответом».
 *
 * Asked to recognise `key` and offered `accommodation`, `neighbourhood` and `responsibility`, a
 * learner picks the short one and never reads it. It got worse the day the option pool became the
 * whole catalogue: a four-letter target could suddenly be offered anything the language has.
 *
 * Measured in CHARACTERS: an option is one lexical item, and its visual weight on the card is its
 * spelling — a wrap is a function of characters and the option's width. The tolerance is ±,
 * relative to the TARGET, and it is configuration (`config/learning.php → distractor_length`)
 * rather than a constant: a product judgement about how hard a card should be, and the first time
 * it is wrong it should move without a deploy of the domain.
 *
 * ## What happens when nothing fits
 *
 * Nothing is substituted. The band is a refusal, not a preference: a candidate outside it is not a
 * worse option, it is an option that answers the card by its shape. A choice card that cannot be
 * filled reaches farther ({@see \App\Modules\Learning\Application\Service\StudyCardAssembler}) and
 * is dropped only when even the catalogue has nothing
 * ({@see \App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentDistractorReader}).
 */
final readonly class DistractorLength
{
    /** ± this share of the target's characters. */
    public const DEFAULT_CHAR_TOLERANCE = 0.5;

    public function __construct(private float $charTolerance = self::DEFAULT_CHAR_TOLERANCE) {}

    /** May `$candidate` stand beside `$target` on the same card? */
    public function fits(string $target, string $candidate): bool
    {
        return self::within(self::chars($target), self::chars($candidate), $this->charTolerance);
    }

    /**
     * `$have` is within `$tolerance` of `$want`.
     *
     * A target that measures zero has no band to speak of — nothing is «±50 % of nothing», and
     * refusing everything here would drop cards over a data defect rather than over a length.
     */
    private static function within(int $want, int $have, float $tolerance): bool
    {
        return $want === 0 || abs($have - $want) <= $want * $tolerance;
    }

    private static function chars(string $text): int
    {
        return mb_strlen(trim($text));
    }
}
