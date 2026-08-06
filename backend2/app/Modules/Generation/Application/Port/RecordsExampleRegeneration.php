<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Records one example regeneration — this is what makes it count against the daily generation quota
 * (the quota reader counts these rows too) and writes the spend.
 */
interface RecordsExampleRegeneration
{
    public function record(
        UserId $userId,
        TermId $termId,
        string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        DateTimeImmutable $at,
    ): void;
}
