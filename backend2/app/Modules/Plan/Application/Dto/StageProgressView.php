<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One stage of a day: how many cards it has, how many are answered, whether it is the current one — and, for a day
 * already dealt, the cards themselves in position order (наряд SESSION-1a, D-04; empty for a day not opened: the
 * outline has no ids).
 */
final readonly class StageProgressView
{
    public const LOCKED = 'locked';

    public const CURRENT = 'current';

    public const DONE = 'done';

    public const ABSENT = 'absent';

    /** @param list<CardView> $cards */
    public function __construct(
        public string $stage,
        public int $total,
        public int $done,
        public string $state,
        public array $cards = [],
    ) {}
}
