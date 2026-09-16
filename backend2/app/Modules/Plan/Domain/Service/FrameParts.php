<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * THE WORDS OF A FRAME OUTSIDE ITS SLOT (наряд SESSION-1a, разд. 1 и 4): what the learner must say for the frame to
 * have been said, whatever went into the window. The voice cards cover it («зачёт голосом — покрытие части
 * каркаса; окно — любое»), the judge checks it before the model is asked, the assembly card lays it out as tiles.
 *
 * `I'd like a ___, please.` → `I'd like a, please`: the mark the sentence ends with is not a word, the slot is not a
 * word, and a space left before a comma by the removed slot is typography.
 */
final class FrameParts
{
    public static function part(string $frameTarget): string
    {
        $text = (string) preg_replace(FrameText::SLOT_PATTERN, ' ', FrameText::withoutEndMark($frameTarget));
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = (string) preg_replace('/\s+([.,!?;:…])/u', '$1', $text);

        return trim($text);
    }

    /** @return list<string> the frame's words outside the slot, in their order and spelling */
    public static function words(string $frameTarget): array
    {
        return Words::surface(self::part($frameTarget));
    }

    /** How many of the frame's words stand before its slot — where the window goes among the tiles; 0 for no slot. */
    public static function slotAt(string $frameTarget): int
    {
        $parts = preg_split(FrameText::SLOT_PATTERN, $frameTarget, 2);
        if (! is_array($parts) || count($parts) < 2) {
            return 0;
        }

        return count(Words::surface($parts[0]));
    }
}
