<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class GetCollection
{
    public function __construct(
        public CollectionId $collectionId,
        public UserId $actorId,
    ) {}
}
