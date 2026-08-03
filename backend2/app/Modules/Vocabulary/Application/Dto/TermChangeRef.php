<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

use DateTimeImmutable;

/** A changed term's id + timestamp for delta sync; content is hydrated via TermContentReader. */
final readonly class TermChangeRef
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $updatedAt,
    ) {}
}
