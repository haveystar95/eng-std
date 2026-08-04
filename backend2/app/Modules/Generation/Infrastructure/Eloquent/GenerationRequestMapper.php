<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\ValueObject\GenerationStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class GenerationRequestMapper
{
    public function toEntity(GenerationRequestModel $model): GenerationRequest
    {
        return GenerationRequest::reconstitute(
            id: GenerationRequestId::fromString($model->id),
            userId: UserId::fromString($model->user_id),
            prompt: $model->prompt,
            normalizedPrompt: $model->normalized_prompt,
            sourceLang: new LanguageCode($model->source_lang),
            targetLang: new LanguageCode($model->target_lang),
            levels: array_map('strval', $model->levels),
            size: $model->size,
            deliveredCount: $model->delivered_count,
            promptVersion: $model->prompt_version,
            status: GenerationStatus::from($model->status),
            model: $model->model,
            tokensIn: $model->tokens_in,
            tokensOut: $model->tokens_out,
            costUsd: $model->cost_usd,
            collectionId: $model->collection_id !== null ? CollectionId::fromString($model->collection_id) : null,
            error: $model->error,
            rawResponse: $model->raw_response,
            createdAt: $model->created_at->toDateTimeImmutable(),
            finishedAt: $model->finished_at?->toDateTimeImmutable(),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(GenerationRequest $request): array
    {
        return [
            'user_id' => $request->userId()->value,
            'prompt' => $request->prompt(),
            'normalized_prompt' => $request->normalizedPrompt(),
            'source_lang' => $request->sourceLang()->value,
            'target_lang' => $request->targetLang()->value,
            'levels' => $request->levels(),
            'size' => $request->size(),
            'delivered_count' => $request->deliveredCount(),
            'prompt_version' => $request->promptVersion(),
            'status' => $request->status()->value,
            'model' => $request->model(),
            'tokens_in' => $request->tokensIn(),
            'tokens_out' => $request->tokensOut(),
            'cost_usd' => $request->costUsd(),
            'collection_id' => $request->collectionId()?->value,
            'error' => $request->error(),
            'raw_response' => $request->rawResponse(),
            'created_at' => $request->createdAt(),
            'finished_at' => $request->finishedAt(),
        ];
    }
}
