<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Loads the progress snapshot (state, interval, due date) for a set of terms, so callers
 * can derive per-collection progress. Terms with no row are simply absent from the result.
 */
interface ProgressSnapshotReader
{
    /**
     * @param  list<string>  $termIds
     * @return array<string, DueTermView>  keyed by term id
     */
    public function forTerms(UserId $userId, array $termIds): array;
}
