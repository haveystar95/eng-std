<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/** A walked stage of a day (`plan_stage_passages`), for the admin's plan page (наряд ADM-1). */
final readonly class InspectedPassage
{
    public function __construct(
        public string $dayId,
        public string $stage,
        public ?string $conversationId,
        public DateTimeImmutable $passedAt,
    ) {}
}
