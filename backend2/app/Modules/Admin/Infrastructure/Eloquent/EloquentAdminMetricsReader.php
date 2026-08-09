<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Port\AdminMetricsReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fleet counts, read as projections. Deliberately reads other modules' read tables directly (a
 * cross-cutting reporting surface, like Observability); it imports no other module's classes.
 */
final class EloquentAdminMetricsReader implements AdminMetricsReader
{
    public function userCount(): int
    {
        return DB::table('users')->count();
    }

    public function collectionCount(): int
    {
        return DB::table('collections')->whereNull('deleted_at')->count();
    }

    public function termCount(): int
    {
        return DB::table('terms')->count();
    }

    public function reviewsSince(DateTimeImmutable $since): int
    {
        return DB::table('reviews')->where('answered_at', '>=', $since)->count();
    }
}
