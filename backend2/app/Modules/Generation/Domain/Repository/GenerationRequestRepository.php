<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Repository;

use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;

interface GenerationRequestRepository
{
    public function findById(GenerationRequestId $id): ?GenerationRequest;

    public function save(GenerationRequest $request): void;

    /**
     * The collection produced by the most recent succeeded request with the same normalized prompt,
     * language pair and prompt version — the term set to reuse instead of calling the model again.
     * `null` when there's no such hit. A prompt_version bump naturally misses (a fresh generation).
     */
    public function findCacheableCollection(
        string $normalizedPrompt,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        string $promptVersion,
    ): ?CollectionId;
}
