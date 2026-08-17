<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * The greatest client_seq the server has stored for a user, per append-only log. The client
 * seeds its monotonic counter from this on login so a reinstall (counter reset to 0) or a
 * second device cannot emit sequences that lose to already-stored rows. 0 means nothing logged.
 */
final readonly class SyncCursorView
{
    public function __construct(
        public int $maxTriageSeq,
        public int $maxReviewSeq,
    ) {}
}
