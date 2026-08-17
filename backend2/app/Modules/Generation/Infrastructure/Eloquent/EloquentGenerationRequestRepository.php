<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;

final class EloquentGenerationRequestRepository implements GenerationRequestRepository
{
    public function __construct(private readonly GenerationRequestMapper $mapper) {}

    public function findById(GenerationRequestId $id): ?GenerationRequest
    {
        $model = GenerationRequestModel::query()->find($id->value);

        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function findCacheableCollection(
        string $normalizedPrompt,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        string $promptVersion,
    ): ?CollectionId {
        $model = GenerationRequestModel::query()
            ->where('normalized_prompt', $normalizedPrompt)
            ->where('source_lang', $sourceLang->value)
            ->where('target_lang', $targetLang->value)
            ->where('prompt_version', $promptVersion)
            ->where('status', 'succeeded')
            ->whereNotNull('collection_id')
            ->orderByDesc('created_at')
            ->first();

        if ($model === null || $model->collection_id === null) {
            return null;
        }

        return CollectionId::fromString($model->collection_id);
    }

    public function save(GenerationRequest $request): void
    {
        GenerationRequestModel::query()->updateOrCreate(
            ['id' => $request->id()->value],
            $this->mapper->toAttributes($request),
        );
    }
}
