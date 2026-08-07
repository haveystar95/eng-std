<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * Server-VAD turn-detection parameters for a realtime session. Tuned (longer silence before the
 * model takes its turn) so it doesn't cut the learner off mid-sentence. Values come from config.
 */
final readonly class RealtimeVad
{
    public function __construct(
        public int $silenceMs,
        public float $threshold,
        public int $prefixPaddingMs,
    ) {}
}
