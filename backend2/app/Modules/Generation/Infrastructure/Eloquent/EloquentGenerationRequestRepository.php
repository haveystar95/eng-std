<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

final class EloquentGenerationRequestRepository implements GenerationRequestRepository
{
    public function __construct(private readonly GenerationRequestMapper $mapper) {}

    public function findById(GenerationRequestId $id): ?GenerationRequest
    {
        $model = GenerationRequestModel::query()->find($id->value);

        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function save(GenerationRequest $request): void
    {
        GenerationRequestModel::query()->updateOrCreate(
            ['id' => $request->id()->value],
            $this->mapper->toAttributes($request),
        );
    }
}
