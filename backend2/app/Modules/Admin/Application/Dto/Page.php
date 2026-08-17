<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One page of a paginated admin listing.
 *
 * `nextCursor` is set only in keyset mode: the id to pass back as `cursor` for the next page, or
 * null when the last page has been reached. In offset mode it stays null and `total`/`page` drive
 * the pager.
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
        public ?string $nextCursor = null,
    ) {}

    /**
     * Build the page a keyset read returns: `nextCursor` is the last row's id, and null once the
     * reader came back short of the requested limit (that was the final page).
     *
     * @template R
     * @param  list<R>  $items
     * @return self<R>
     */
    public static function keyset(array $items, ListWindow $window, ?string $lastId, int $total = 0): self
    {
        return new self(
            items: $items,
            total: $total,
            page: 1,
            perPage: $window->limit,
            nextCursor: count($items) < $window->limit ? null : $lastId,
        );
    }
}
