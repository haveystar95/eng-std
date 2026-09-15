<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * The sound of one line as bought (TTS-2): its bytes and format, the voice it is filed under, how long it sounds, and
 * its bill — the characters of its text, the credits the vendor debited for it (`character-cost`), their price, and
 * the vendor's id of the call (distinct ids count the calls paid for).
 */
final readonly class SpokenAudio
{
    public function __construct(
        public string $bytes,
        public string $format,
        public string $voiceKey,
        public ?int $durationMs,
        public int $characters,
        public int $credits,
        public string $costUsd,
        public ?string $requestId,
    ) {}
}
