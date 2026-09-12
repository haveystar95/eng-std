<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** What one backfill run did: scenes looked at, tones written, copies fetched, what is still missing. */
final readonly class SceneImageBackfillReport
{
    public function __construct(
        public int $scenes,
        public int $tonesWritten,
        public int $tonesMissing,
        public int $copiesFetched,
        public int $copiesMissing,
    ) {}
}
