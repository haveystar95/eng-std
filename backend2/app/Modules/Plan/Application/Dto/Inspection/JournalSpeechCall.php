<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/** An outbound text-to-speech call from the request log: the voice asked, the text sent, the audio size returned. */
final readonly class JournalSpeechCall
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
