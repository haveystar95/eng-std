<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Domain\ValueObject\GenerationStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;

final class InMemoryGenerationRequestRepository implements GenerationRequestRepository
{
    /** @var array<string, GenerationRequest> */
    private array $byId = [];

    public function findById(GenerationRequestId $id): ?GenerationRequest
    {
        return $this->byId[$id->value] ?? null;
    }

    public function findCacheableCollection(
        string $normalizedPrompt,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        string $promptVersion,
    ): ?CollectionId {
        foreach (array_reverse($this->byId) as $request) {
            if ($request->status() === GenerationStatus::Succeeded
                && $request->collectionId() !== null
                && $request->normalizedPrompt() === $normalizedPrompt
                && $request->sourceLang()->value === $sourceLang->value
                && $request->targetLang()->value === $targetLang->value
                && $request->promptVersion() === $promptVersion
            ) {
                return $request->collectionId();
            }
        }

        return null;
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
