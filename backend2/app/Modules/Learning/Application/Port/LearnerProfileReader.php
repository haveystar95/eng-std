<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The slice of the user's Identity profile that Learning needs — narrow on purpose, so
 * Learning never grows a dependency on the whole profile. A second method (the daily new-term
 * goal) is added when the quota work needs it.
 */
interface LearnerProfileReader
{
    public function cefrLevelFor(UserId $user): CefrLevel;
}
