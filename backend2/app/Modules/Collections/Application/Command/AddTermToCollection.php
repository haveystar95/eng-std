<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class AddTermToCollection
{
    public function __construct(
        public CollectionId $collectionId,
        public TermId $termId,
        public UserId $actorId,
        public ?string $note = null,
    ) {}
}
