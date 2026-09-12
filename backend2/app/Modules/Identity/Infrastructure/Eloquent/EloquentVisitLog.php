<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Port\VisitLog;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentVisitLog implements VisitLog
{
    public function lastVisitAtForUpdate(UserId $user): ?DateTimeImmutable
    {
        // The user's row is the lock: `user_visits` has no row to lock before the first visit.
        DB::table('users')->where('id', $user->value)->lockForUpdate()->value('id');

        $at = DB::table('user_visits')->where('user_id', $user->value)->max('visited_at');

        return $at === null ? null : new DateTimeImmutable((string) $at);
    }

    public function append(UserId $user, DateTimeImmutable $visitedAt): void
    {
        DB::table('user_visits')->insert([
            'id' => Ulid::generate(),
            'user_id' => $user->value,
            'visited_at' => $visitedAt->format(DATE_ATOM),
        ]);
    }

    public function latest(UserId $user, int $limit): array
    {
        $utc = new DateTimeZone('UTC');

        return array_values(DB::table('user_visits')
            ->where('user_id', $user->value)
            ->orderByDesc('visited_at')
            ->limit($limit)
            ->pluck('visited_at')
            ->map(static fn (mixed $at): DateTimeImmutable => (new DateTimeImmutable((string) $at))->setTimezone($utc))
            ->all());
    }
}
