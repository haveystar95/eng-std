<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\ImageQuery;

/** Stock photos for English descriptions — the same seam collections use. */
interface PlanImageFinder
{
    public function find(string $prompt): ?Image;

    /**
     * Many searches at once, six on the wire at most (DAY-UI-3): a day's photos come together. One
     * answer per query, in order; null — nothing matched. A transient vendor error that outlives the
     * adapter's retries propagates.
     *
     * @param  list<ImageQuery>  $queries
     * @return list<Image|null>
     */
    public function findMany(array $queries): array;

    /**
     * The tone of a photo found earlier, asked of the vendor by the photo's own address — for the
     * photos stored before tones were kept. Null when the address names no photo the vendor knows,
     * or the vendor could not answer now (the backfill runs again).
     */
    public function tone(string $imageUrl): ?string;
}
