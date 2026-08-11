<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * How much of a listing to return and where to start.
 *
 * Two modes, one object, so every reader takes the same parameter:
 *  - CURSOR (the panel's infinite scroll): `cursor` is the last id of the previous page and rows
 *    come back ordered by id DESC — a keyset walk that can't skip or repeat a row when new rows
 *    land while scrolling. A null cursor with `keyset` true is simply the first page.
 *  - OFFSET (legacy `page`/`per_page`): kept so existing consumers of the read endpoints keep
 *    working unchanged.
 *
 * `limit` is the row count in both modes.
 */
final readonly class ListWindow
{
    public function __construct(
        public int $limit,
        public int $page = 1,
        public ?string $cursor = null,
        public bool $keyset = false,
    ) {}

    /** Rows to skip in offset mode; always 0 in keyset mode. */
    public function offset(): int
    {
        return $this->keyset ? 0 : max(0, ($this->page - 1) * $this->limit);
    }
}
