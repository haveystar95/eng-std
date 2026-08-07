<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Repository;

use App\Modules\Generation\Domain\Entity\PracticeDialog;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use DateTimeImmutable;

interface PracticeDialogRepository
{
    public function findById(PracticeDialogId $id): ?PracticeDialog;

    public function save(PracticeDialog $dialog): void;

    /**
     * Still-active dialogs whose token TTL lapsed before $now — the background expiry sweep's input.
     *
     * @return list<PracticeDialog>
     */
    public function staleActive(DateTimeImmutable $now, int $limit): array;
}
