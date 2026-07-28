<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Fetch one generation for its owner (used by GET /generations/{id} and the console). */
final readonly class GetGenerationRequest
{
    public function __construct(
        public GenerationRequestId $id,
        public UserId $actorId,
    ) {}
}
