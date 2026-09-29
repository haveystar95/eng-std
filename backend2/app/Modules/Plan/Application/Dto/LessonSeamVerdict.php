<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Check\LessonViolation;

/**
 * What the seam judge said of a day: `judged` — it answered, and every native sentence it said does not read is a
 * finding `filler.native_seam` at its filler, every reply it said names a filler of its question a finding
 * `partner.names_filler_meaning` at its partner line (наряд GEN-4c); `nothing` — the day has no native sentence and no reply to
 * read (no call); `unavailable` — the call failed or its answer was not the shape (paid for all the same, no finding, counted
 * as `judge.unavailable`). `judged` counts the sentences it gave a verdict on, `items` the sentences sent, `replies` the
 * replies sent; `naming` — the ids of the replies it said name a filler.
 */
final readonly class LessonSeamVerdict
{
    public const JUDGED = 'judged';

    public const NOTHING = 'nothing';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @param  list<LessonViolation>  $violations
     * @param  list<string>  $naming
     */
    public function __construct(
        public string $status,
        public array $violations,
        public int $items,
        public int $judged,
        public string $costUsd,
        public int $latencyMs,
        public string $note = '',
        public int $replies = 0,
        public array $naming = [],
    ) {}
}
