<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Identity\Application\Port\PushDelivery;
use App\Modules\Identity\Application\Port\PushTokenStore;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * `PUT /devices/push-token`: the address is upserted; a token seen under another account moves to this one.
 *
 * Answers whether push is ENABLED for this address — the token is stored and the server has a live
 * APNs sender. The phone takes that answer as the switch between the two ways a reminder reaches the
 * learner: true — the server sends, the phone removes its local reminders; false — the phone keeps
 * scheduling them. One answer, so the learner never gets the same reminder twice.
 */
final readonly class RegisterPushTokenHandler
{
    public function __construct(
        private PushTokenStore $tokens,
        private Clock $clock,
        private PushDelivery $delivery,
    ) {}

    /** @return bool push_enabled */
    public function __invoke(RegisterPushToken $command): bool
    {
        $token = trim($command->token);
        $this->tokens->upsert(
            $command->userId,
            $command->platform,
            $token,
            $command->locale,
            $command->timezone,
            $this->clock->now(),
        );

        return $token !== '' && $this->delivery->isLive();
    }
}
