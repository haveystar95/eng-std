<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

final readonly class CheckCounterRow
{
    public function __construct(
        public string $promptVersion,
        public string $check,
        public string $action,
        public int $hits,
    ) {}
}
