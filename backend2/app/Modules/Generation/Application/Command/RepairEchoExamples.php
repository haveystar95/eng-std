<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Give a real example to every term in a collection whose example teaches nothing — because it is
 * the term repeated back (QA-7), or because it is missing, which after {@see DraftValidator} is the
 * same fact one step later: an echo was refused at the door.
 *
 * Scoped to a collection because that is the unit a person can look at and judge, and it is what
 * both callers have: the post-generation chain has just built one, and the console command is
 * pointed at one by hand.
 */
final readonly class RepairEchoExamples
{
    public function __construct(
        public UserId $actorId,
        public CollectionId $collectionId,
        /** Count what would be repaired and spend nothing. */
        public bool $dryRun = false,
    ) {}
}
