<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Repository;

use App\Modules\Generation\Domain\Entity\PracticeDialog;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

interface PracticeDialogRepository
{
    public function findById(PracticeDialogId $id): ?PracticeDialog;

    public function save(PracticeDialog $dialog): void;

    /**
     * The user's most recent concluded (finished|expired) dialog for this collection — the "result"
     * shown on the collection. Null when they've never had one. Owner-scoped by user_id.
     */
    public function lastConcludedForCollection(UserId $userId, CollectionId $collectionId): ?PracticeDialog;

    /**
     * Still-active dialogs whose token TTL lapsed before $now — the background expiry sweep's input.
     *
     * @return list<PracticeDialog>
     */
    public function staleActive(DateTimeImmutable $now, int $limit): array;
}
