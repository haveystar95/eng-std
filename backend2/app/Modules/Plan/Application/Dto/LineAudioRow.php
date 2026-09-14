<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One spoken line on the disk: `lineRef` is the unit it voices — `x3` the partner's line of exchange 3,
 * `x3b` the learner's, `p2` a phrase, `v5` a word or chunk (DAY-UI-3) — and `voiceKey` whose voice it is.
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
