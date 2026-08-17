<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The result of the user's last concluded practice dialog for a collection. */
final readonly class GetLastCollectionDialog
{
    public function __construct(
        public UserId $userId,
        public CollectionId $collectionId,
    ) {}
}
