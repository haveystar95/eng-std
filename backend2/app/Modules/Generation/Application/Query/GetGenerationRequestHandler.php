<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Generation\Application\Dto\GenerationRequestView;
use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;

final readonly class GetGenerationRequestHandler
{
    public function __construct(private GenerationRequestRepository $requests) {}

    public function __invoke(GetGenerationRequest $query): ?GenerationRequestView
    {
        $request = $this->requests->findById($query->id);

        // Owner-only: a non-owner (or missing id) is indistinguishable from not-found.
        if ($request === null || ! $request->userId()->equals($query->actorId)) {
            return null;
        }

        return $this->toView($request);
    }

    private function toView(GenerationRequest $request): GenerationRequestView
    {
        return new GenerationRequestView(
            id: $request->id()->value,
            status: $request->status()->value,
            prompt: $request->prompt(),
            requested: $request->size(),
            delivered: $request->deliveredCount(),
            collectionId: $request->collectionId()?->value,
            error: $request->error(),
            model: $request->model(),
            tokensIn: $request->tokensIn(),
            tokensOut: $request->tokensOut(),
            costUsd: $request->costUsd(),
            createdAt: $request->createdAt(),
            finishedAt: $request->finishedAt(),
        );
    }
}
