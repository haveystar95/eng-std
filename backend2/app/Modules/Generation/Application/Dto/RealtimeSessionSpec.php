<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What to mint a realtime session with. The adapter renders the versioned prompt file against
 * {@see $lesson} to produce the model's instructions, and expires the token after {@see $ttlSeconds}
 * — that TTL is the whole duration guard for the practice session.
 */
final readonly class RealtimeSessionSpec
{
    /** @param array<string, mixed> $lesson */
    public function __construct(
        public string $model,
        public string $transcribeModel,
        public string $voice,
        public int $ttlSeconds,
        public RealtimeVad $vad,
        public array $lesson,
    ) {}
}
