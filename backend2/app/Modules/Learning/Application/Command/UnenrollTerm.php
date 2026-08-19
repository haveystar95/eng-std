<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Take one term out of the learner's pool — «Убрать из изучения».
 *
 * A PAUSE. The history stays, the rung stays, the schedule stays; only the pool membership is
 * cleared, so the word stops being dealt and can be brought back exactly where it was left.
 */
final readonly class UnenrollTerm
{
    public function __construct(
        public UserId $actorId,
        public TermId $termId,
    ) {}
}
