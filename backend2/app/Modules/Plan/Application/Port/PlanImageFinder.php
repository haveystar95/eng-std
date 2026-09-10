<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\ValueObject\Image;

/** One stock photo for an English description, or null — the same seam collections use. */
interface PlanImageFinder
{
    public function find(string $prompt): ?Image;
}
