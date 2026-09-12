<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\ValueObject\Image;

/** One stock photo for an English description, or null — the same seam collections use. */
interface PlanImageFinder
{
    public function find(string $prompt): ?Image;

    /**
     * The tone of a photo found earlier, asked of the vendor by the photo's own address — for the
     * photos stored before tones were kept. Null when the address names no photo the vendor knows,
     * or the vendor could not answer now (the backfill runs again).
     */
    public function tone(string $imageUrl): ?string;
}
