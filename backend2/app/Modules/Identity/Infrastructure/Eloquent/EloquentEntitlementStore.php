<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Port\EntitlementStore;
use App\Modules\Identity\Domain\ValueObject\Entitlement;
use App\Modules\Identity\Domain\ValueObject\EntitlementProduct;
use App\Modules\Identity\Domain\ValueObject\EntitlementSource;
use App\Modules\Identity\Domain\ValueObject\EntitlementStatus;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/** `entitlements` through the query builder: one row per (user, source), rewritten in place by a grant. */
final class EloquentEntitlementStore implements EntitlementStore
{
    public function forUser(UserId $userId): array
    {
        return array_values(DB::table('entitlements')
            ->where('user_id', $userId->value)
            ->orderBy('created_at')->orderBy('id')
            ->get()
            ->map(static fn (object $row): Entitlement => new Entitlement(
                source: EntitlementSource::from((string) $row->source),
                product: EntitlementProduct::from((string) $row->product),
                status: EntitlementStatus::from((string) $row->status),
                startsAt: new DateTimeImmutable((string) $row->starts_at),
                expiresAt: $row->expires_at === null ? null : new DateTimeImmutable((string) $row->expires_at),
                updatedAt: new DateTimeImmutable((string) $row->updated_at),
            ))
            ->all());
    }

    public function put(UserId $userId, Entitlement $entitlement): void
    {
        DB::table('entitlements')->upsert(
            [[
                'id' => Ulid::generate(),
                'user_id' => $userId->value,
                'source' => $entitlement->source->value,
                'product' => $entitlement->product->value,
                'status' => $entitlement->status->value,
                'starts_at' => $entitlement->startsAt->format(DATE_ATOM),
                'expires_at' => $entitlement->expiresAt?->format(DATE_ATOM),
                'created_at' => $entitlement->updatedAt->format(DATE_ATOM),
                'updated_at' => $entitlement->updatedAt->format(DATE_ATOM),
            ]],
            ['user_id', 'source'],
            ['product', 'status', 'starts_at', 'expires_at', 'updated_at'],
        );
    }

    public function revokeAll(UserId $userId, DateTimeImmutable $now): int
    {
        $at = $now->format(DATE_ATOM);

        return DB::table('entitlements')
            ->where('user_id', $userId->value)
            ->whereIn('status', [EntitlementStatus::Active->value, EntitlementStatus::Grace->value])
            ->where(static fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $at))
            ->update(['status' => EntitlementStatus::Expired->value, 'expires_at' => $at, 'updated_at' => $at]);
    }
}
