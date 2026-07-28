<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Partial update of a collection's title/description (owner only). */
final readonly class UpdateCollection
{
    public function __construct(
        public CollectionId $collectionId,
        public UserId $actorId,
        public ?string $title = null,
        public ?string $description = null,
    ) {}
}
