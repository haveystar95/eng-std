<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

/**
 * The result of asking for a generation: the request id, and whether this call actually created it.
 * `created` is false when a client-supplied id was already seen — the caller then returns the
 * existing request (200) and does NOT dispatch a second job or spend quota again.
 */
final readonly class GenerationRequestOutcome
{
    public function __construct(
        public GenerationRequestId $id,
        public bool $created,
    ) {}
}
