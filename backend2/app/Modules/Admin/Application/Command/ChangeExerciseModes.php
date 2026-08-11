<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * An admin flipping trainer toggles. `userId` null = the product default; `modes` null = drop this
 * user's override so they inherit again.
 */
final readonly class ChangeExerciseModes
{
    /** @param list<string>|null $modes */
    public function __construct(
        public string $adminId,
        public ?UserId $userId,
        public ?array $modes,
    ) {}
}
