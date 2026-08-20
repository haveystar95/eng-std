<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One collection on the «Сводка» screen: how much of its content is ready, and what a догон costs. */
final readonly class ContentHealthCollectionRow
{
    public function __construct(
        public string $id,
        public string $title,
        public string $type,              // system | shared | custom
        public int $terms,
        public int $withoutExample,
        public int $pickCorrectReady,
        public int $needsEnrichment,
        public float $estimatedTopUpUsd,
    ) {}
}
