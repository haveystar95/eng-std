<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\AdminUserCollectionRow;
use App\Modules\Admin\Application\Dto\AdminUserProfileRow;
use App\Modules\Admin\Application\Dto\AdminUserRow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\ProgressStateCounts;
use App\Modules\Admin\Application\Port\AdminUserReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Admin projections over the user/profile/progress tables. A cross-cutting reporting reader (imports
 * no other module's classes); joins stay within a single module's own tables.
 */
final class EloquentAdminUserReader implements AdminUserReader
{
    public function list(?string $search, int $page, int $perPage): Page
    {
        $base = DB::table('users as u')->leftJoin('profiles as p', 'p.user_id', '=', 'u.id');
        if ($search !== null && $search !== '') {
            $base->where('u.email', 'ILIKE', '%' . $search . '%');
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->orderByDesc('u.created_at')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get(['u.id', 'u.name', 'u.email', 'u.created_at', 'p.tier', 'p.cefr_level']);

        $ids = array_values(array_map(static fn (stdClass $r): string => (string) $r->id, $rows->all()));
        $collectionCounts = $this->collectionCounts($ids);
        $progressCounts = $this->countByUser('user_term_progress', $ids);

        $items = array_map(function (stdClass $r) use ($collectionCounts, $progressCounts): AdminUserRow {
            $id = (string) $r->id;

            return new AdminUserRow(
                id: $id,
                name: (string) $r->name,
                email: $r->email !== null ? (string) $r->email : null,
                tier: $r->tier !== null ? (string) $r->tier : 'free',
                cefr: $r->cefr_level !== null ? (string) $r->cefr_level : null,
                createdAt: Iso::orNull($r->created_at),
                collectionsCount: $collectionCounts[$id] ?? 0,
                progressCount: $progressCounts[$id] ?? 0,
            );
        }, $rows->all());

        return new Page(array_values($items), $total, $page, $perPage);
    }

    public function profile(string $userId): ?AdminUserProfileRow
    {
        $r = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.id', $userId)
            ->first([
                'u.id', 'u.name', 'u.email', 'u.avatar', 'u.created_at',
                'p.tier', 'p.cefr_level', 'p.daily_goal', 'p.timezone', 'p.onboarded_at',
            ]);
        if ($r === null) {
            return null;
        }

        return new AdminUserProfileRow(
            id: (string) $r->id,
            name: (string) $r->name,
            email: $r->email !== null ? (string) $r->email : null,
            avatar: $r->avatar !== null ? (string) $r->avatar : null,
            tier: $r->tier !== null ? (string) $r->tier : 'free',
            cefr: $r->cefr_level !== null ? (string) $r->cefr_level : null,
            dailyGoal: (int) ($r->daily_goal ?? 0),
            timezone: $r->timezone !== null && $r->timezone !== '' ? (string) $r->timezone : 'UTC',
            onboardedAt: Iso::orNull($r->onboarded_at),
            createdAt: Iso::orNull($r->created_at),
        );
    }

    public function progressStates(string $userId): ProgressStateCounts
    {
        /** @var array<string, int> $byState */
        $byState = DB::table('user_term_progress')
            ->where('user_id', $userId)
            ->groupBy('state')
            ->selectRaw('state, count(*) AS c')
            ->pluck('c', 'state')
            ->map(static fn ($c): int => (int) $c)
            ->all();

        return new ProgressStateCounts(
            total: array_sum($byState),
            learning: $byState['learning'] ?? 0,
            review: $byState['review'] ?? 0,
            relearning: $byState['relearning'] ?? 0,
            known: $byState['known'] ?? 0,
        );
    }

    public function reviewsTotal(string $userId): int
    {
        return DB::table('reviews')->where('user_id', $userId)->count();
    }

    public function collectionsOf(string $userId): array
    {
        // A user's library = the collections they own (custom) plus the ones they've added
        // (user_collections). Owned-but-unsubscribed collections have no pivot row, so a left join
        // and an OR keeps both; added_at falls back to the collection's own created_at when owned.
        $rows = DB::table('collections as c')
            ->leftJoin('user_collections as uc', function (JoinClause $join) use ($userId): void {
                $join->on('uc.collection_id', '=', 'c.id')->where('uc.user_id', '=', $userId);
            })
            ->where(function (Builder $q) use ($userId): void {
                $q->where('c.owner_id', $userId)->orWhereNotNull('uc.user_id');
            })
            ->whereNull('c.deleted_at')
            ->orderByDesc('c.created_at')
            ->get(['c.id', 'c.title', 'c.type', 'c.items_count', 'uc.added_at', 'c.created_at']);

        return array_values(array_map(static fn (stdClass $r): AdminUserCollectionRow => new AdminUserCollectionRow(
            id: (string) $r->id,
            title: (string) $r->title,
            type: (string) $r->type,
            itemsCount: (int) $r->items_count,
            addedAt: Iso::orNull($r->added_at ?? $r->created_at),
        ), $rows->all()));
    }

    /**
     * Library size per user: owned collections plus added (subscribed) ones.
     *
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    private function collectionCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<string, int> $owned */
        $owned = DB::table('collections')
            ->whereIn('owner_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('owner_id')
            ->selectRaw('owner_id, count(*) AS c')
            ->pluck('c', 'owner_id')
            ->map(static fn ($c): int => (int) $c)
            ->all();

        $subscribed = $this->countByUser('user_collections', $ids);

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ($owned[$id] ?? 0) + ($subscribed[$id] ?? 0);
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    private function countByUser(string $table, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<string, int> $counts */
        $counts = DB::table($table)
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) AS c')
            ->pluck('c', 'user_id')
            ->map(static fn ($c): int => (int) $c)
            ->all();

        return $counts;
    }
}
