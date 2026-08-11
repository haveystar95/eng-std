<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http;

use App\Modules\Admin\Application\Dto\ListWindow;
use Illuminate\Http\Request;

/**
 * Parses a listing's window from the query string, in either mode:
 *  - `limit` (and optionally `cursor`) → keyset mode, what the panel's infinite scroll uses;
 *  - `page` / `per_page` → the original offset mode, still honoured for existing consumers.
 *
 * Absent both, it is offset mode page 1 — so an untouched caller behaves exactly as before.
 */
final class Paging
{
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    public static function of(Request $request): ListWindow
    {
        $keyset = $request->has('limit') || $request->has('cursor');

        $limit = $keyset
            ? $request->integer('limit', self::DEFAULT_PER_PAGE)
            : $request->integer('per_page', self::DEFAULT_PER_PAGE);
        $limit = min(self::MAX_PER_PAGE, max(1, $limit));

        $cursor = $request->string('cursor')->toString();

        return new ListWindow(
            limit: $limit,
            page: max(1, $request->integer('page', 1)),
            cursor: $cursor !== '' ? $cursor : null,
            keyset: $keyset,
        );
    }
}
