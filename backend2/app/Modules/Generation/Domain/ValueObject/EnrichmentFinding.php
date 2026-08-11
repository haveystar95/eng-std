<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/** One thing a person has to decide about one term. Carries its own prose — the report prints it. */
final readonly class EnrichmentFinding
{
    public function __construct(
        public string $termId,
        public FindingKind $kind,
        public ?string $field,
        public string $detail,
    ) {}
}
