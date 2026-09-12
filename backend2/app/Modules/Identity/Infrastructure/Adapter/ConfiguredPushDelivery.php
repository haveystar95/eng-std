<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Adapter;

use App\Modules\Identity\Application\Port\PushDelivery;

/**
 * Live exactly when `services.apns.key_p8` is set — the same condition by which the Plan module binds
 * the real APNs sender instead of the dry run, read from the same shared config key.
 */
final class ConfiguredPushDelivery implements PushDelivery
{
    public function isLive(): bool
    {
        return trim((string) config('services.apns.key_p8', '')) !== '';
    }
}
