<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Deletes a user's account and all of their data across every module, atomically. The concrete
 * implementation fans out to each module's own eraser (never a raw query on another module's
 * table) and revokes the user's tokens. Terms stay global — only their authorship link is dropped.
 */
interface AccountEraser
{
    public function eraseFor(UserId $userId): void;
}
