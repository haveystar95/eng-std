<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One stage of a day: its cards, how many are answered, and whether it is the current one. */
final readonly class StageProgressView
{
    public const LOCKED = 'locked';

    public const CURRENT = 'current';

    public const DONE = 'done';

    public const ABSENT = 'absent';

    public function __construct(
        public string $stage,
        public int $total,
        public int $done,
        public string $state,
    ) {}
}
