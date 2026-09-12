<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Push;

use App\Modules\Plan\Application\Dto\DeliveryResult;
use App\Modules\Plan\Application\Dto\PushMessage;
use App\Modules\Plan\Application\Port\PushSender;
use App\Modules\Plan\Domain\ValueObject\DeliveryStatus;
use Illuminate\Support\Facades\Log;

/**
 * DRY MODE — bound while there is no APNs key (`APNS_KEY_P8` empty). The app is signed with a free
 * Personal Team today: no Push capability, no device token, no .p8. The letter is written to the
 * application log in full — who, what, the exact title and body, how many addresses it would have
 * gone to — and the delivery is logged as `not_sent`. Everything before this door (the journal, the
 * tick, the queue, the texts, the log) runs exactly as it will with the key.
 */
final class DryRunPushSender implements PushSender
{
    public const REASON = 'dry_run: APNS_KEY_P8 is not configured';

    public function send(PushMessage $message, array $targets): DeliveryResult
    {
        Log::info('plan push (dry run) — letter not sent', [
            'user_id' => $message->userId,
            'kind' => $message->kind->value,
            'title' => $message->title,
            'body' => $message->body,
            'plan_id' => $message->planId,
            'day_number' => $message->dayNumber,
            'tokens' => count($targets),
        ]);

        return new DeliveryResult(DeliveryStatus::NotSent, self::REASON);
    }
}
