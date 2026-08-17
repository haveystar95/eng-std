<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Erase everything Collections holds for a user on account deletion: their owned collections
 * (and items, by cascade) and every store subscription. Global/system collections are untouched.
 */
interface CollectionsAccountEraser
{
    public function eraseFor(UserId $userId): void;
}
