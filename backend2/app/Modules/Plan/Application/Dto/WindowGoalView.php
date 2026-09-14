<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One «научишься …» line; `passed` only once the day is passed. */
final readonly class WindowGoalView
{
    public function __construct(
        public string $text,
        public bool $passed,
    ) {}
}
