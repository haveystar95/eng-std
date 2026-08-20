<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * How many terms carry a given enrichment mark. `version` is null for «станок не прогонялся» — the
 * bucket that matters most, and the one an inner join would have silently dropped.
 */
final readonly class ContentVersionCount
{
    public function __construct(
        public ?string $version,
        public int $terms,
    ) {}
}
