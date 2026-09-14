<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\CheckCounterRow;
use App\Modules\Plan\Domain\ValueObject\Finding;

/**
 * How often each check fires, per prompt version — what the admin panel reads before anyone
 * switches a plan check from `observe` to `drop` or `gate`, or makes a lesson code fatal.
 */
interface CheckCounters
{
    /** @param list<Finding> $findings the plan checks' findings, counted by check and action */
    public function record(string $promptVersion, array $findings): void;

    /**
     * The lesson validator's findings, counted by code (action `counted` — the validator never acts).
     *
     * @param  list<string>  $codes  one entry per finding
     */
    public function recordCodes(string $promptVersion, array $codes): void;

    /** @return list<CheckCounterRow> */
    public function all(): array;
}
