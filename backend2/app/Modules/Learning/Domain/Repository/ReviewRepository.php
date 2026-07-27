<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\Review;

interface ReviewRepository
{
    /**
     * Append a review to the log. Idempotent by client-supplied ULID:
     * returns true if this call inserted the row, false if it already existed
     * (ON CONFLICT DO NOTHING). Never updates an existing review.
     */
    public function insertIgnore(Review $review): bool;
}
