<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Domain\Exception\AccountNotFound;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Deletes a user's account and all of their data across every module, atomically (наряд ACC-1 §1). The concrete
 * implementation fans out to each module's own eraser (never a raw query on another module's table) and revokes the
 * user's tokens. Terms stay global — only their authorship link is dropped; the request log and the admin audit stay,
 * unlinked; the model call journal (`model_calls`) names no user and stays as it is. One line of
 * `account_deletions` counts the deletion.
 *
 * @throws AccountNotFound when the account is gone already — the repeat of a deletion (404)
 */
interface AccountEraser
{
    public function eraseFor(UserId $userId): void;
}
