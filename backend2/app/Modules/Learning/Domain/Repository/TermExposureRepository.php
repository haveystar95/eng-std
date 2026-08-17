<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\TermExposure;

/**
 * The append-only intro log. Like `reviews` and `term_triages`: inserted, never updated,
 * never deleted outside account erasure.
 */
interface TermExposureRepository
{
    /**
     * Append an exposure. Returns false when the pair was already exposed — the row is left
     * exactly as it was, keeping the FIRST `shown_at`, which is the one that is true.
     */
    public function insertIgnore(TermExposure $exposure): bool;
}
