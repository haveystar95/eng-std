<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Drop one user's override row for one mode, so that mode inherits the global row again. */
final readonly class ClearModeOverride
{
    public function __construct(
        public UserId $userId,
        public string $mode,
    ) {}
}
