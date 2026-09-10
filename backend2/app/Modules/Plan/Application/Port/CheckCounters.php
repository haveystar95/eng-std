<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\CheckCounterRow;
use App\Modules\Plan\Domain\ValueObject\Finding;

/**
 * How often each check fires, per prompt version — what the admin panel reads before anyone
 * switches a check from `observe` to `drop` or `gate`.
 */
interface CheckCounters
{
    /** @param list<Finding> $findings */
    public function record(string $promptVersion, array $findings): void;

    /** @return list<CheckCounterRow> */
    public function all(): array;
}
