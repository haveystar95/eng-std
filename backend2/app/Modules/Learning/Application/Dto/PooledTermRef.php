<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;

/**
 * A term that entered the learner's POOL, with the term's own `updated_at`.
 *
 * Learning's own shape rather than Vocabulary's `TermChangeRef`, for the same reason Collections
 * keeps a `SubscribedTermRef`: the reader that builds it lives in Infrastructure, and Learning's
 * Infrastructure may not reach into another module's Application. The sync handler maps it across
 * the boundary, where that translation belongs.
 */
final readonly class PooledTermRef
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $updatedAt,
    ) {}
}
