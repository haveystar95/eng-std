<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Erase everything Learning holds for a user on account deletion: progress, reviews, triages,
 * sessions and derived daily stats. The append-only logs (reviews, term_triages) are never
 * *updated*; a full account erase removes them wholesale — a lifecycle event, not a domain edit.
 */
interface LearningAccountEraser
{
    public function eraseFor(UserId $userId): void;
}
