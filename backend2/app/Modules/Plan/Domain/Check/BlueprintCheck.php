<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Blueprint\Blueprint;

/** One check over the plan builder's answer — same three modes as a lesson check. */
interface BlueprintCheck
{
    public function name(): string;

    public function switchable(): bool;

    /** @return list<string> */
    public function violations(Blueprint $blueprint, BlueprintContext $context): array;

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint;
}
