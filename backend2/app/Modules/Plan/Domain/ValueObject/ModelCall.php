<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * What one accepted model answer cost and which build produced it — stamped on the plan or the
 * scene it was written into. Cost is the sum of every attempt (a refused answer cost money too).
 */
final readonly class ModelCall
{
    public function __construct(
        public string $promptVersion,
        public string $buildVersion,
        public string $model,
        /** USD with six decimals, as `ModelCost` prices it. */
        public string $costUsd,
        public int $latencyMs,
        public int $attempts,
    ) {}

    public static function addCosts(string $a, string $b): string
    {
        return number_format((float) $a + (float) $b, 6, '.', '');
    }
}
