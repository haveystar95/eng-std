<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\SyncCursorView;
use App\Modules\Shared\Domain\ValueObject\UserId;

interface SyncCursorReader
{
    /** The greatest client_seq stored per log for this user (0 when the log is empty). */
    public function cursorFor(UserId $userId): SyncCursorView;
}
