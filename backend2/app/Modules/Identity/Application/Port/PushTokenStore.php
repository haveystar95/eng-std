<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Application\Dto\PushTokenView;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** The devices' push addresses. Keyed by (platform, token): a token belongs to whoever registered it last. */
interface PushTokenStore
{
    /** Insert, or move the existing (platform, token) row to `$user` and refresh it. */
    public function upsert(UserId $user, string $platform, string $token, ?string $locale, ?string $timezone, DateTimeImmutable $seenAt): void;

    /** Delete the address — only the owner's row when `$owner` is given, any row when null (a dead token). */
    public function remove(string $platform, string $token, ?UserId $owner): void;

    /** @return list<PushTokenView> newest seen first */
    public function forUser(UserId $user): array;
}
