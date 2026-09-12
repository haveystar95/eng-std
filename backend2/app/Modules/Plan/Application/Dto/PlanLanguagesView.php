<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

final readonly class PlanLanguagesView
{
    /** @param list<string> $targets language codes a plan may be built in */
    public function __construct(
        public array $targets,
    ) {}
}
