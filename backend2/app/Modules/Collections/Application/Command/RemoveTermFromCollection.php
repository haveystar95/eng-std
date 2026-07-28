<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Remove a term from a collection (owner only). The term itself stays in Vocabulary. */
final readonly class RemoveTermFromCollection
{
    public function __construct(
        public CollectionId $collectionId,
        public UserId $actorId,
        public TermId $termId,
    ) {}
}
