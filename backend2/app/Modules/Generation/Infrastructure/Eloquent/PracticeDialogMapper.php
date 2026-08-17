<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Domain\Entity\PracticeDialog;
use App\Modules\Generation\Domain\ValueObject\PracticeDialogStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class PracticeDialogMapper
{
    public function toEntity(PracticeDialogModel $model): PracticeDialog
    {
        return PracticeDialog::reconstitute(
            id: PracticeDialogId::fromString($model->id),
            userId: UserId::fromString($model->user_id),
            collectionId: CollectionId::fromString($model->collection_id),
            status: PracticeDialogStatus::from($model->status),
            lesson: $model->lesson_json,
            expiresAt: $model->expires_at->toDateTimeImmutable(),
            tokensIn: $model->tokens_in,
            tokensOut: $model->tokens_out,
            costUsd: $model->cost_usd,
            createdAt: $model->created_at->toDateTimeImmutable(),
            finishedAt: $model->finished_at?->toDateTimeImmutable(),
            summary: $model->summary,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(PracticeDialog $dialog): array
    {
        return [
            'user_id' => $dialog->userId()->value,
            'collection_id' => $dialog->collectionId()->value,
            'status' => $dialog->status()->value,
            'lesson_json' => $dialog->lesson(),
            'expires_at' => $dialog->expiresAt(),
            'tokens_in' => $dialog->tokensIn(),
            'tokens_out' => $dialog->tokensOut(),
            'cost_usd' => $dialog->costUsd(),
            'created_at' => $dialog->createdAt(),
            'finished_at' => $dialog->finishedAt(),
            'summary' => $dialog->summary(),
        ];
    }
}
