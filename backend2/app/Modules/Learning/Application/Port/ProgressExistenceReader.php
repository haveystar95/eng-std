<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Tells which of a set of terms the user has actually started (a progress row in a state
 * other than `new`). A missing row and a `new` row both mean "never studied" and are equally
 * eligible as a "new" card — a term returned from `known` keeps its row (and its reps/lapses)
 * as `new`, and must still surface as new, exactly as if it had no row.
 */
interface ProgressExistenceReader
{
    /**
     * @param  list<string>  $termIds
     * @return array<string, true>  the subset already started (non-`new` row), as a lookup set
     */
    public function existingTermIds(UserId $userId, array $termIds): array;
}
