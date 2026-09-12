<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Dto\PushTokenView;
use App\Modules\Identity\Application\Port\PushTokenStore;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentPushTokenStore implements PushTokenStore
{
    public function upsert(UserId $user, string $platform, string $token, ?string $locale, ?string $timezone, DateTimeImmutable $seenAt): void
    {
        // One statement, so two devices racing on the same token cannot both insert. The id is
        // only ever minted for a new address; a moved row keeps its id and changes its owner.
        DB::statement(
            'INSERT INTO device_push_tokens (id, user_id, platform, token, locale, timezone, last_seen_at, created_at, updated_at) '
            .'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) '
            .'ON CONFLICT (platform, token) DO UPDATE SET user_id = EXCLUDED.user_id, locale = EXCLUDED.locale, '
            .'timezone = EXCLUDED.timezone, last_seen_at = EXCLUDED.last_seen_at, updated_at = EXCLUDED.updated_at',
            [
                Ulid::generate(), $user->value, $platform, $token, $locale, $timezone,
                $seenAt->format(DATE_ATOM), $seenAt->format(DATE_ATOM), $seenAt->format(DATE_ATOM),
            ],
        );
    }

    public function remove(string $platform, string $token, ?UserId $owner): void
    {
        DB::table('device_push_tokens')
            ->where('platform', $platform)
            ->where('token', $token)
            ->when($owner !== null, static fn ($q) => $q->where('user_id', $owner?->value))
            ->delete();
    }

    public function forUser(UserId $user): array
    {
        return array_values(DB::table('device_push_tokens')
            ->where('user_id', $user->value)
            ->orderByDesc('last_seen_at')
            ->get(['platform', 'token', 'locale', 'timezone', 'last_seen_at'])
            ->map(static fn (object $row): PushTokenView => new PushTokenView(
                platform: (string) $row->platform,
                token: (string) $row->token,
                locale: $row->locale === null ? null : (string) $row->locale,
                timezone: $row->timezone === null ? null : (string) $row->timezone,
                lastSeenAt: (new DateTimeImmutable((string) $row->last_seen_at))->format(DATE_ATOM),
            ))
            ->all());
    }
}
