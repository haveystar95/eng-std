<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One stage a day on the route has: `words`…`speak`, and `done` | `current` | `locked`. */
final readonly class RouteStageView
{
    public function __construct(
        public string $stage,
        public string $state,
    ) {}
}
