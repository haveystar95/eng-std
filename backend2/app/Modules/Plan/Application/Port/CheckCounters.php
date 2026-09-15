<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\CheckCounterRow;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\Finding;

/**
 * How often each check fires, per prompt version — what the admin panel reads before anyone
 * switches a plan check from `observe` to `drop` or `gate`, or moves a lesson code between fatal and warning.
 */
interface CheckCounters
{
    /** @param list<Finding> $findings the plan checks' findings, counted by check and action */
    public function record(string $promptVersion, array $findings): void;

    /**
     * The lesson validator's findings, counted by code: `counted` for every finding, `gated` / `failed` for the
     * fatal ones that held the day or were left when it failed.
     *
     * @param  list<string>  $codes  one entry per finding
     */
    public function recordCodes(string $promptVersion, array $codes, CheckAction $action = CheckAction::Counted): void;

    /** @return list<CheckCounterRow> */
    public function all(): array;
}
