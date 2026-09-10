<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

final readonly class LineAudioRow
{
    public function __construct(
        public string $id,
        public string $sceneId,
        public int $step,
        public string $voiceKey,
        public string $format,
        public string $path,
        public ?int $durationMs,
    ) {}
}
