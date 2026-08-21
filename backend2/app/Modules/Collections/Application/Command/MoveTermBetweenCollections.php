<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Move one term from one of the learner's own folders to another of them.
 *
 * A move and not a copy: the point of folders is that a saved word lives somewhere, and «перенести»
 * on the word card is how the learner corrects where. Both ends are checked for ownership, so this
 * can never pull a word out of a store deck or push one into someone else's folder.
 *
 * It is deliberately a single command rather than remove+add from the client: the two halves must
 * not be able to half-happen offline, leaving the word in neither folder.
 */
final readonly class MoveTermBetweenCollections
{
    public function __construct(
        public CollectionId $fromCollectionId,
        public CollectionId $toCollectionId,
        public TermId $termId,
        public UserId $actorId,
    ) {}
}
