<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Is the QA tooling open for this account — the account is marked `is_qa` AND the environment is
 * not production with the dev flag on. The port asks the one place that folds both locks
 * ({@see \App\Modules\Identity\Application\Port\UserReader}, `qa_tools`), so a second rule about
 * the same door cannot drift open.
 */
interface QaToolsDoor
{
    public function isOpenFor(UserId $user): bool;
}
