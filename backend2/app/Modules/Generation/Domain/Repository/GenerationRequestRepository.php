<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Repository;

use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

interface GenerationRequestRepository
{
    public function findById(GenerationRequestId $id): ?GenerationRequest;

    public function save(GenerationRequest $request): void;
}
