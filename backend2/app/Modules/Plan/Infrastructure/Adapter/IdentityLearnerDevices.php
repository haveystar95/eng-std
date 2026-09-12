<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Identity\Application\Command\RemovePushToken;
use App\Modules\Identity\Application\Command\RemovePushTokenHandler;
use App\Modules\Identity\Application\Dto\PushTokenView;
use App\Modules\Identity\Application\Query\GetPushTokens;
use App\Modules\Identity\Application\Query\GetPushTokensHandler;
use App\Modules\Plan\Application\Dto\PushTarget;
use App\Modules\Plan\Application\Port\LearnerDevices;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The learner's push addresses, read and pruned through Identity's Application — Identity owns devices. */
final readonly class IdentityLearnerDevices implements LearnerDevices
{
    public function __construct(
        private GetPushTokensHandler $tokens,
        private RemovePushTokenHandler $remove,
    ) {}

    public function targetsFor(UserId $user): array
    {
        return array_map(
            static fn (PushTokenView $t): PushTarget => new PushTarget($t->platform, $t->token),
            ($this->tokens)(new GetPushTokens($user)),
        );
    }

    public function forget(PushTarget $target): void
    {
        ($this->remove)(new RemovePushToken($target->platform, $target->token, null));
    }
}
