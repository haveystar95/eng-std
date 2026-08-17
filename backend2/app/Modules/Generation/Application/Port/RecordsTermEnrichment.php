<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;
use DateTimeImmutable;

/** Persists the spend of one term enrichment (so "cost is written", like generation requests). */
interface RecordsTermEnrichment
{
    public function record(
        TermId $termId,
        string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        DateTimeImmutable $at,
    ): void;
}
