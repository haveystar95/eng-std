<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * One append-only line of a dialog transcript. `ts` is the client's own timestamp for the line
 * (milliseconds); (role, ts) is the idempotency key, so re-uploading a batch inserts nothing new.
 */
final readonly class TranscriptLine
{
    public function __construct(
        public TranscriptRole $role,
        public string $text,
        public int $ts,
    ) {}
}
