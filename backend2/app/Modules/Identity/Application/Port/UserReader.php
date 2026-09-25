<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Application\Dto\UserView;
use App\Modules\Shared\Domain\ValueObject\UserId;

interface UserReader
{
    public function byId(UserId $id): ?UserView;

    /** The account with this email (the owner's tools name a learner by it — `access:grant`), or null. */
    public function byEmail(string $email): ?UserView;
}
