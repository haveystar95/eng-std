<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Application\Dto\ProfileInput;
use App\Modules\Identity\Application\Dto\UserView;
use App\Modules\Shared\Domain\ValueObject\UserId;

interface ProfileUpdater
{
    /** Apply a partial update to the user's profile and return the refreshed user. */
    public function update(UserId $id, ProfileInput $input): UserView;
}
