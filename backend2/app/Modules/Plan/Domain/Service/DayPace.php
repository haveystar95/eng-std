<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\Stage;

/**
 * HOW LONG THE DAY STILL TAKES — the one rule behind every «≈ N мин» of the day window (DAY-UI-2).
 *
 * Seconds per card by stage, from the one day walked live on the phone (13.09: 82 cards in 26
 * minutes, pauses over ten minutes left out): words 8.3 s, phrases 28.8, the dialogue 34, listen
 * 13.4, speak 40.8. A flat rate would promise the same minutes for thirty word cards (four minutes
 * of tapping) and for eight exchanges said aloud (five and a half) — the stage the learner is on is
 * exactly where that error shows. Minutes round up: a stage with a card left never says «0».
 */
final class DayPace
{
    private const SECONDS_PER_CARD = [
        'words' => 8,
        'phrases' => 29,
        'dialogue' => 34,
        'listen' => 13,
        'speak' => 41,
    ];

    public static function seconds(Stage $stage, int $cards): int
    {
        return max(0, $cards) * self::SECONDS_PER_CARD[$stage->value];
    }

    public static function minutes(int $seconds): int
    {
        return $seconds <= 0 ? 0 : (int) ceil($seconds / 60);
    }
}
