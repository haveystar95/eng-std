<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

final class InMemoryGenerationRequestRepository implements GenerationRequestRepository
{
    /** @var array<string, GenerationRequest> */
    private array $byId = [];

    public function findById(GenerationRequestId $id): ?GenerationRequest
    {
        return $this->byId[$id->value] ?? null;
    }

    public function save(GenerationRequest $request): void
    {
        $this->byId[$request->id()->value] = $request;
    }

    public function count(): int
    {
        return count($this->byId);
    }
}
