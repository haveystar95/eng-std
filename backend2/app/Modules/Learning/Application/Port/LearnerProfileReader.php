<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The slice of the user's Identity profile that Learning needs — narrow on purpose, so Learning
 * never grows a dependency on the whole profile.
 */
interface LearnerProfileReader
{
    public function cefrLevelFor(UserId $user): CefrLevel;

    /**
     * The user's daily new-term quota — one global cap, not per collection. Zero means "reviews
     * only, introduce nothing new"; clamped to a sane maximum so a single session can't seed the
     * whole dictionary.
     */
    public function newTermsPerDay(UserId $user): int;
}
