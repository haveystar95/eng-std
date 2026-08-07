<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Start a realtime practice dialog over a collection. The client generates {@see $id} (ULID). */
final readonly class StartPracticeDialog
{
    public function __construct(
        public UserId $userId,
        public CollectionId $collectionId,
        public PracticeDialogId $id,
    ) {}
}
