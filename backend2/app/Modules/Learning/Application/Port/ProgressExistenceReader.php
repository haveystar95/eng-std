<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Tells which of a set of terms the user has actually started — a question about the ACQUISITION
 * ladder, not about the scheduler. A pair that has been shown its intro card has started even
 * though SM-2 has never touched it. A missing row and an `acquisition = 'new'` row both mean
 * "never shown" and are equally eligible as a "new" card, which is what keeps a term returned
 * from `known` (its row and its reps/lapses survive) surfacing as new exactly as if it had none.
 */
interface ProgressExistenceReader
{
    /**
     * @param  list<string>  $termIds
     * @return array<string, true>  the subset already introduced, as a lookup set
     */
    public function existingTermIds(UserId $userId, array $termIds): array;
}
