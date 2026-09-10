<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

final readonly class SpokenAudio
{
    public function __construct(
        public string $bytes,
        public string $format,
        public string $voiceKey,
        public ?int $durationMs,
        public ?string $costUsd,
    ) {}
}
