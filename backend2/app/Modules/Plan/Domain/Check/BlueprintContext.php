<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

/** What was ORDERED from the plan builder: how many scenes, and after which existing ones. */
final readonly class BlueprintContext
{
    public function __construct(
        public int $scenesCount,
        public int $existingScenes = 0,
    ) {}
}
