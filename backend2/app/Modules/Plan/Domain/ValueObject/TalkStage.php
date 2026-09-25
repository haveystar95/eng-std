<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHERE A DAY'S SIXTH STAGE STANDS (наряд CONV-2, п. 2) — read off the journal of walked stages first and off the talks
 * second, never the other way round:
 *
 * - `passed` — the day has a passage for the talk ({@see StagePassage}); a replay going on beside it changes nothing;
 * - `skipped` — the day has a passage with no talk (наряд ACC-1 §3, {@see StagePassage::skipsTalk()}): nothing to talk
 *   about, or dealt on five stages before the talk existed — the stage does not hold the day shut and is not drawn;
 * - `open` — a talk was started and the stage is not walked yet;
 * - `ahead` — nothing was started.
 *
 * CONV-1 read the stage off «the latest talk is over», and «Ещё раз» made a walked stage «идёт» again.
 */
enum TalkStage: string
{
    case Ahead = 'ahead';
    case Open = 'open';
    case Passed = 'passed';
    case Skipped = 'skipped';

    /** @param bool $started is there any talk of the day at all */
    public static function of(?StagePassage $passage, bool $started): self
    {
        return match (true) {
            $passage?->skipsTalk() === true => self::Skipped,
            $passage !== null => self::Passed,
            $started => self::Open,
            default => self::Ahead,
        };
    }
}
