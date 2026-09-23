<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/** One bought line of a scene with its bill (`plan_line_audios`), for the admin's plan page (наряд ADM-1). */
final readonly class InspectedAudio
{
    public function __construct(
        public string $id,
        public string $sceneId,
        public string $lineRef,
        public string $voiceKey,
        public string $format,
        public int $bytes,
        public ?int $durationMs,
        public ?int $characters,
        public ?int $credits,
        public ?string $costUsd,
        public ?string $requestId,
        public ?DateTimeImmutable $createdAt,
    ) {}
}
