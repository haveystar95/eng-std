<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Learning\Domain\ValueObject\TriageId;
use App\Modules\Learning\Domain\ValueObject\TriageVerdict;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use DateTimeImmutable;

/** One triage swipe as it crosses into the application from the client (already in VOs). */
final readonly class TriageInput
{
    public function __construct(
        public TriageId $triageId,
        public TermId $termId,
        public TriageVerdict $verdict,
        public DateTimeImmutable $decidedAt,
        public int $clientSeq,
        public ?CollectionId $collectionId = null,
        public ?int $latencyMs = null,
    ) {}
}
