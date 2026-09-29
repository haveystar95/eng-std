<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `partner.pairs_many` — FATAL (a shape guard beside the order's list, наряд GEN-4, found by its gate run). Every frame a
 * partner line pairs with gets a partner line of its own. The dialogue says a partner line once, in one exchange, and a
 * learner line stands on one frame; DIALOGUE_COUNT gives a line one exchange. So the frames the lines pair with are matched
 * to the lines, one each: when they cannot be — «Für Sie passt das Basiskonto. Es kostet vier Euro im Monat» paired with
 * «Welches Konto passt zu ___?» and «Was kostet ___ im Monat?» and no other line with either — a frame is left with no
 * exchange it may stand in: the dialogue cannot say the line again (`partner.twice`) nor answer it with a line of its own
 * (`partner.unlinked`), and the day failed on `frame.unused` after two paid dialogues. A line paired with two frames is
 * fine when another line takes the other one (two questions, each pairing with both answers).
 */
final class PartnerPairsMany implements SkeletonRule
{
    public const CODE = 'partner.pairs_many';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return true;
    }

    public function findings(Skeleton $skeleton, SkeletonContext $context): array
    {
        /** @var array<string, list<string>> $lines a partner line's id → the frames it pairs with */
        $lines = [];
        foreach ($skeleton->partnerLines as $line) {
            $frames = [];
            foreach ($line->pairsWith as $number) {
                $frame = $skeleton->frameOfItem($number);
                if ($frame !== null) {
                    $frames[$frame->id()] = true;
                }
            }
            if ($frames !== []) {
                $lines[$line->id] = array_keys($frames);
            }
        }
        $paired = array_values(array_unique(array_merge([], ...array_values($lines))));

        /** @var array<string, string> $match a frame → the line it stands with */
        $match = [];
        foreach (array_keys($lines) as $lineId) {
            $seen = [];
            self::place($lineId, $lines, $match, $seen);
        }
        $alone = array_values(array_diff($paired, array_keys($match)));
        if ($alone === []) {
            return [];
        }
        $crowded = array_filter($lines, static fn (array $frames): bool => count($frames) > 1);
        $how = implode('; ', array_map(static fn (string $id, array $frames): string => "{$id} pairs with ".implode(' and ', $frames), array_keys($crowded), $crowded));
        sort($alone);

        return [new LessonViolation(self::CODE, 'skeleton', 'no partner line of its own is left for '.implode(', ', $alone)." ({$how}): a line is said once, in one exchange, with one frame")];
    }

    /**
     * Stand the line with a frame of its own, moving another line to its other frame when that frees one (a matching).
     *
     * @param  array<string, list<string>>  $lines
     * @param  array<string, string>  $match
     * @param  array<string, true>  $seen
     */
    private static function place(string $lineId, array $lines, array &$match, array &$seen): bool
    {
        foreach ($lines[$lineId] as $frame) {
            if (isset($seen[$frame])) {
                continue;
            }
            $seen[$frame] = true;
            if (! isset($match[$frame]) || self::place($match[$frame], $lines, $match, $seen)) {
                $match[$frame] = $lineId;

                return true;
            }
        }

        return false;
    }
}
