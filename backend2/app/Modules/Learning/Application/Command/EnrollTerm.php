<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Put one term into the learner's pool — «Учить это слово».
 *
 * The second of the two doors into the pool (the first is a triage swipe). Both are deliberate acts
 * by the learner; nothing else — not adding a collection, not generating one, not answering a
 * practice card — ever enrols a word.
 */
final readonly class EnrollTerm
{
    public function __construct(
        public UserId $actorId,
        public TermId $termId,
    ) {}
}
