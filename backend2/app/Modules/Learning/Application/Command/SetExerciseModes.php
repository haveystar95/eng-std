<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Flip the trainer toggles.
 *
 * `userId` null targets the product default; otherwise it is that user's override. `modes` null is
 * only meaningful for a user and means DROP the override (inherit again) — the global default can
 * never be "unset", there is nothing above it to inherit from.
 *
 * @param  list<string>|null  $modes
 */
final readonly class SetExerciseModes
{
    /** @param list<string>|null $modes */
    public function __construct(
        public ?UserId $userId,
        public ?array $modes,
    ) {}
}
