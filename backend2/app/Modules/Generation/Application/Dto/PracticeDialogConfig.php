<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * The realtime-session knobs, resolved from config at the composition root and injected so the
 * Application layer never reads the framework. TTL is the session duration guard; the model and
 * voice pick the realtime engine and its voice; maxTargetWords caps how many words a lesson briefs.
 */
final readonly class PracticeDialogConfig
{
    public function __construct(
        public string $realtimeModel,
        public string $transcribeModel,
        public string $voice,
        public int $ttlSeconds,
        public int $maxTargetWords,
    ) {}
}
