<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One page of a paginated admin listing.
 *
 * @template T
 */
final readonly class Page
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
