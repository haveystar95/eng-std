<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

/** Record a terminal failure (called after the job's retries are exhausted). */
final readonly class FailGeneration
{
    public function __construct(
        public GenerationRequestId $id,
        public string $reason,
    ) {}
}
