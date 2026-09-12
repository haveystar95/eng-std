<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\DeliveryResult;
use App\Modules\Plan\Application\Dto\PushMessage;
use App\Modules\Plan\Application\Dto\PushTarget;

/**
 * The door to the phone. `ApnsPushSender` when the APNs key is configured, `DryRunPushSender`
 * (the letter goes to the log, `not_sent`) when it is not — the queue and the log are the same
 * either way, so the day the key appears nothing else changes.
 */
interface PushSender
{
    /** @param list<PushTarget> $targets every address of the learner; may be empty */
    public function send(PushMessage $message, array $targets): DeliveryResult;
}
