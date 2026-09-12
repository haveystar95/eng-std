<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

/**
 * Does this deployment actually deliver push letters? True only when an APNs key is configured —
 * without it every letter is a dry run in the log. The registration answers `push_enabled` from it,
 * and the phone decides by that answer whether its own local reminders are still needed.
 */
interface PushDelivery
{
    public function isLive(): bool;
}
