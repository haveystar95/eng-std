<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Support;

use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Runs an admin listing over a prepared query in whichever mode the request asked for.
 *
 * Both modes order by id DESC. Ids here are ULIDs, so that is newest-first — the same order the
 * lists always had (`created_at DESC`) but on a strictly unique, monotonic key, which is what makes
 * the keyset walk safe: `WHERE id < :cursor` can never skip a row or hand one out twice while rows
 * are being inserted mid-scroll, the way OFFSET does.
 *
 * `total` is counted in both modes: these tables are small, and the panel shows the count next to
 * an infinite-scrolling list.
 */
final class Keyset
{
    /**
     * @template T
     * @param  list<string>  $columns  columns to select
     * @param  callable(list<stdClass>): list<T>  $map
     * @return Page<T>
     */
    public static function page(
        Builder $base,
        ListWindow $window,
        string $idColumn,
        array $columns,
        callable $map,
    ): Page {
        $total = (clone $base)->count();

        $q = (clone $base)->orderByDesc($idColumn)->limit($window->limit);
        if ($window->keyset) {
            if ($window->cursor !== null) {
                $q->where($idColumn, '<', $window->cursor);
            }
        } else {
            $q->offset($window->offset());
        }

        /** @var list<stdClass> $rows */
        $rows = array_values($q->get($columns)->all());
        $items = $map($rows);

        if (! $window->keyset) {
            return new Page($items, $total, $window->page, $window->limit);
        }

        $last = $rows === [] ? null : $rows[count($rows) - 1];
        $lastId = $last !== null ? (string) $last->{self::alias($idColumn)} : null;

        return Page::keyset($items, $window, $lastId, $total);
    }

    /** `u.id` selects as `id`; the cursor is read off the returned row by that bare name. */
    private static function alias(string $column): string
    {
        $pos = strrpos($column, '.');

        return $pos === false ? $column : substr($column, $pos + 1);
    }
}
