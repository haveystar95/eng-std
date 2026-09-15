<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Check\LessonViolation;

/**
 * What the seam judge said of a day: `judged` — it answered, and every native sentence it said does not read is a
 * finding `filler.native_seam` at its filler; `nothing` — the day has no native sentence to read (no call);
 * `unavailable` — the call failed or its answer was not the shape (paid for all the same, no finding, counted as
 * `judge.unavailable`). `judged` counts the sentences it gave a verdict on, `items` the sentences sent.
 */
final readonly class LessonSeamVerdict
{
    public const JUDGED = 'judged';

    public const NOTHING = 'nothing';

    public const UNAVAILABLE = 'unavailable';

    /** @param list<LessonViolation> $violations */
    public function __construct(
        public string $status,
        public array $violations,
        public int $items,
        public int $judged,
        public string $costUsd,
        public int $latencyMs,
        public string $note = '',
    ) {}
}
