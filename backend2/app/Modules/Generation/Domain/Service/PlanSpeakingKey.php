<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\PlanDayItem;

/**
 * WHAT A SPOKEN LINE IS JUDGED ON — the one string, chosen once, when the day is written.
 *
 * A plan line used to be graded by coverage of the whole sentence. The owner's screen on 01.09 is
 * what that costs: «Yes, I'm looking for a place to rent for long-term living in a new country»,
 * seven of fifteen words underlined as missing, «Не то», three sittings in a row — under a caption
 * that says the card is checking whether the WORD was remembered. The card teaches the piece in the
 * hole and it was marking the other fourteen words.
 *
 * So the key is the piece, and the three answers are tried in this order:
 *
 *   1. THE FILLER. A line with a frame is a frame plus one card of this day, and that card is what
 *      the line exists to drill.
 *   2. A DAY CARD INSIDE THE LINE. A formula has no hole, but «I need to move in next month.»
 *      written as a formula still stands on «next month». The LONGEST match wins — «close to the
 *      city center» over «city», because the longer one is the card the day actually teaches.
 *   3. NOTHING. «Sorry, could you repeat that?» is a whole move and there is no piece to pick out
 *      of it; the trainer then asks for the line entire and says so on the card.
 *
 * Pure, and in Domain next to the validator that judges the same pair, so «what is this line's key»
 * has one answer at write time, at grade time and on the screen.
 */
final class PlanSpeakingKey
{
    /**
     * @param  list<PlanDayItem>  $items  every card of the day, the line included
     * @return string|null  the key, or null when the whole line is the ask
     */
    public static function of(PlanDayItem $line, array $items): ?string
    {
        if ($line->kind !== PlanDayItem::KIND_LINE) {
            return null;
        }

        if ($line->hasSlot() && trim($line->filler) !== '') {
            return $line->filler;
        }

        $best = null;
        foreach ($items as $item) {
            if ($item->kind === PlanDayItem::KIND_LINE || trim($item->text) === '') {
                continue;
            }
            if (! self::containsPhrase($line->text, $item->text)) {
                continue;
            }
            if ($best === null || mb_strlen($item->text) > mb_strlen($best)) {
                $best = $item->text;
            }
        }

        return $best;
    }

    /**
     * Is the card's text in this line as a whole phrase?
     *
     * On word boundaries, the same rule {@see DescriptionSelfReference} matches a headword by, and
     * for the same reason: a card «rent» must be found in «a place to rent» and not in «current».
     * No inflection is tried — the filler of a real frame is copied character for character, and
     * guessing a form here would let the key wander off the card the day teaches.
     */
    private static function containsPhrase(string $haystack, string $needle): bool
    {
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote(trim($needle), '/') . '(?![\p{L}\p{N}])/ui';

        return preg_match($pattern, $haystack) === 1;
    }
}
