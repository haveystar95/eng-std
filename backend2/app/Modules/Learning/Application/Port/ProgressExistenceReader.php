<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Tells which of a set of terms the user already has progress for. A term with no row has
 * never been studied — that is exactly what makes it eligible as a "new" card.
 */
interface ProgressExistenceReader
{
    /**
     * @param  list<string>  $termIds
     * @return array<string, true>  the subset that already has a progress row, as a lookup set
     */
    public function existingTermIds(UserId $userId, array $termIds): array;
}
