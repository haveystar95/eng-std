<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

/**
 * Hands a pending request off to the background worker. The HTTP path uses this so the
 * request returns 202 immediately; the console path runs ProcessGeneration inline instead.
 */
interface DispatchesGeneration
{
    public function dispatch(GenerationRequestId $id): void;
}
