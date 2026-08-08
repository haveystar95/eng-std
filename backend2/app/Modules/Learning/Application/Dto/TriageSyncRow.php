<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;

/**
 * One term's governing triage verdict, shipped in the delta feed so the client can rebuild its
 * local triage marker after a sign-out wipe (the marker is the only thing that keeps an `unknown`
 * swipe — which writes no progress row — from being re-offered in the deck). Append-only on the
 * server, so this is always an upsert; there is no triage tombstone.
 */
final readonly class TriageSyncRow
{
    public function __construct(
        public string $termId,
        public string $verdict,
        public int $clientSeq,
        public ?string $collectionId,
        public DateTimeImmutable $updatedAt,   // server receipt time of the governing row
    ) {}
}
