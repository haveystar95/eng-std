<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Domain\ValueObject\Image;

/**
 * The plan's photos come from the same seam the collections' photos do. A transient search error
 * propagates so the job retries; an empty result is null and stays null.
 */
final readonly class PexelsPlanImageFinder implements PlanImageFinder
{
    public function __construct(private ImageSearchPort $images) {}

    public function find(string $prompt): ?Image
    {
        $found = $this->images->search($prompt);

        return $found === null ? null : new Image($found->url, $found->author, $found->authorUrl);
    }
}
