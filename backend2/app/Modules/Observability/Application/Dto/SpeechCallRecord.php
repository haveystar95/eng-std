<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Dto;

use DateTimeImmutable;

/**
 * One outbound text-to-speech call as the request log holds it: which voice was asked, the text sent, what came back
 * (the status, and the size of the audio the vendor returned — the log keeps `{bytes, binary}` for a binary body).
 */
final readonly class SpeechCallRecord
{
    public function __construct(
        public string $id,
        public string $voiceId,
        public ?string $text,
        public ?int $status,
        public ?int $audioBytes,
        public DateTimeImmutable $occurredAt,
    ) {}
}
