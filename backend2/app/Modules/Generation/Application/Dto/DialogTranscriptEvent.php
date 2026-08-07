<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One transcript event as the client sends it. `role` is the raw string ('user'|'assistant');
 * the handler resolves it to a {@see \App\Modules\Generation\Domain\ValueObject\TranscriptRole}
 * and drops anything it doesn't recognise. `ts` is the client's line timestamp (ms).
 */
final readonly class DialogTranscriptEvent
{
    public function __construct(
        public string $role,
        public string $text,
        public int $ts,
    ) {}
}
