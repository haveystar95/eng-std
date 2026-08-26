<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;

/** A pair and the instant its repeat is due — Learning's half of «на грани», before content. */
final readonly class ScheduledTermFact
{
    public function __construct(
        public string $termId,
        public DateTimeImmutable $dueAt,
    ) {}
}
