<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE PARTNER'S LINES A STAGE PICKS BY LENGTH (наряд SESSION-1a, разд. 1–2): «Слушаю и отвечаю» plays the longest line
 * of at most ten words at two tempos (`listen_pace`), «Говорю сам» echoes the longest of at most eighteen and retells
 * the next one — never the line the pace card took. One helper for both stages, so the two can never pick by
 * different rules and share a line.
 *
 * Longest by {@see Words::count()}; between lines of one length the lower step wins; a line with no words is no line.
 */
final class PartnerLines
{
    public const PACE_MAX_WORDS = 10;

    public const SPEAK_MAX_WORDS = 18;

    /**
     * The line `listen_pace` plays: the longest of at most {@see PACE_MAX_WORDS} words.
     *
     * @return array{step: int, message: Message}|null
     */
    public static function pace(SceneMaterial $scene): ?array
    {
        return self::longest($scene, self::PACE_MAX_WORDS);
    }

    /**
     * The longest partner line of at most `$maxWords` words whose exchange is not among `$excludeSteps`.
     *
     * @param  list<int>  $excludeSteps
     * @return array{step: int, message: Message}|null
     */
    public static function longest(SceneMaterial $scene, int $maxWords, array $excludeSteps = []): ?array
    {
        $best = null;
        $bestCount = 0;
        foreach ($scene->partnerLines() as $line) {
            if (in_array($line['step'], $excludeSteps, true)) {
                continue;
            }
            $count = Words::count($line['message']->textTarget);
            if ($count === 0 || $count > $maxWords) {
                continue;
            }
            if ($best === null || $count > $bestCount || ($count === $bestCount && $line['step'] < $best['step'])) {
                $best = $line;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
