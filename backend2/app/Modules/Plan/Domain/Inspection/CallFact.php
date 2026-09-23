<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/** A model call read as the plan's (by its build or talk window) — its journal status. */
final readonly class CallFact
{
    public function __construct(
        public string $id,
        public ?int $day,
        public ?string $purpose,
        public string $status,
        public ?string $error,
    ) {}
}
