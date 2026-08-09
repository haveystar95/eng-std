<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http;

use Illuminate\Http\Request;

/** Parses `page` / `per_page` query params with sane defaults and a hard cap. */
final class Paging
{
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    /** @return array{0: int, 1: int}  [page, perPage] */
    public static function of(Request $request): array
    {
        $page = max(1, $request->integer('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->integer('per_page', self::DEFAULT_PER_PAGE)));

        return [$page, $perPage];
    }
}
