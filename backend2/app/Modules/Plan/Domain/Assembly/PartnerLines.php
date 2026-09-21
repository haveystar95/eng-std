<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE PARTNER'S LINE «СЛУШАЮ И ОТВЕЧАЮ» PLAYS AT TWO TEMPOS (наряд SESSION-1a, разд. 1–2): `listen_pace` takes the
 * longest line of at most ten words. Until наряд CONV-2 «Говорю сам» echoed the longest partner line of at most eighteen
 * beside it, and this helper kept the two stages from sharing one; the echo is the learner's own line now
 * ({@see SpeakStage}), and the partner's words are the listening's alone.
 *
 * Longest by {@see Words::count()}; between lines of one length the lower step wins; a line with no words is no line.
 */
final class PartnerLines
{
    public const PACE_MAX_WORDS = 10;

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
     * The longest partner line of at most `$maxWords` words.
     *
     * @return array{step: int, message: Message}|null
     */
    public static function longest(SceneMaterial $scene, int $maxWords): ?array
    {
        $best = null;
        $bestCount = 0;
        foreach ($scene->partnerLines() as $line) {
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
