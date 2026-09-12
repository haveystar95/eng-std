<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** One stage a day on the route actually has, and where it stands. */
final readonly class RouteStage
{
    public function __construct(
        public Stage $stage,
        public StageState $state,
    ) {}
}
