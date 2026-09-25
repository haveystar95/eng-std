<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Identity\Application\Query\GetAccess;
use App\Modules\Identity\Application\Query\GetAccessHandler;
use App\Modules\Plan\Application\Port\LearnerAccess;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The learner's subscription, read through Identity's Application (`GetAccess` — the rights of the learner read by
 * Identity's `AccessRule`): the one rule of «in force», written once, for `GET /auth/me` and for the paywall alike.
 */
final readonly class IdentityLearnerAccess implements LearnerAccess
{
    public function __construct(private GetAccessHandler $access) {}

    public function subscribed(UserId $user): bool
    {
        return ($this->access)(new GetAccess($user))->isPremium();
    }
}
