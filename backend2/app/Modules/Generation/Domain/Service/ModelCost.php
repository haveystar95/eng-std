<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Estimates the USD cost of a model call from its token counts. One pricing table for every AI
 * spend in the module (collection generation, term enrichment) so cost is computed the same way
 * everywhere. Rates are USD per 1K tokens [input, output]; an unknown model or missing counts → null.
 */
final class ModelCost
{
    /** @var array<string, array{0: float, 1: float}> */
    private const PRICING = [
        'gpt-4o' => [0.0025, 0.01],
        'gpt-4o-mini' => [0.00015, 0.0006],
    ];

    public function estimate(string $model, ?int $tokensIn, ?int $tokensOut): ?string
    {
        if (! isset(self::PRICING[$model]) || $tokensIn === null || $tokensOut === null) {
            return null;
        }

        [$inRate, $outRate] = self::PRICING[$model];
        $cost = ($tokensIn / 1000) * $inRate + ($tokensOut / 1000) * $outRate;

        return number_format($cost, 6, '.', '');
    }
}
