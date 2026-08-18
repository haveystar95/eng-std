<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** An admin resetting one user's override cell back to the product default. */
final readonly class ClearModeSettingsOverride
{
    public function __construct(
        public string $adminId,
        public UserId $userId,
        public string $mode,
    ) {}
}
