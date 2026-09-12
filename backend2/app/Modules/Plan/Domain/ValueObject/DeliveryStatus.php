<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * How a letter went (`plan_notifications.status`): `sent` — APNs accepted it for at least one
 * device; `not_sent` — dry mode, no APNs key, the letter went to the log; `failed` — APNs refused
 * it for every device; `no_token` — the key is there, the learner has no device address.
 */
enum DeliveryStatus: string
{
    case Sent = 'sent';
    case NotSent = 'not_sent';
    case Failed = 'failed';
    case NoToken = 'no_token';
}
