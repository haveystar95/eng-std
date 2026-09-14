<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One spoken line on the disk: `lineRef` is the unit it voices — `x3`, the partner's line of exchange 3.
 * (`p2` rows — phrases voiced on 14.09 before the canon «premium voice for the role's lines only» —
 * stay in the table; nothing names them any more.)
 */
final readonly class LineAudioRow
{
    public function __construct(
        public string $id,
        public string $sceneId,
        public string $lineRef,
        public string $voiceKey,
        public string $format,
        public string $path,
        public ?int $durationMs,
    ) {}
}
