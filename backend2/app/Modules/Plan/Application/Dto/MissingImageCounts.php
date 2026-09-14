<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** How many scenes and how many words and chunks of the live (not deleted) plans have no photo. */
final readonly class MissingImageCounts
{
    public function __construct(
        public int $scenes,
        public int $terms,
    ) {}
}
