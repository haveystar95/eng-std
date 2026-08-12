<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

use DateTimeImmutable;

/**
 * A changed term's id + timestamp for delta sync; content is hydrated via TermContentReader.
 *
 * `deleted` marks a RETIRED term — the tombstone an offline device needs to drop it. Without one,
 * an admin deletion leaves the word sitting in every local mirror forever, since a row that simply
 * stops being returned is indistinguishable from a row that never changed.
 */
final readonly class TermChangeRef
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $updatedAt,
        public bool $deleted = false,
    ) {}
}
