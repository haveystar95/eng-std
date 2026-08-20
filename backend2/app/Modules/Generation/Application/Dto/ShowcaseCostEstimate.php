<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What regenerating N terms is expected to cost, priced from the shared table and from token counts
 * that were actually observed rather than guessed.
 *
 * The batch figure is reported beside the live one because OpenAI's Batch API is half price for work
 * that can wait — which a catalogue sweep can. It is an estimate of a saving that is NOT implemented
 * here (see the command), and it is printed so the decision to build it can be made against a number.
 */
final readonly class ShowcaseCostEstimate
{
    /**
     * @param  string  $source  where the token counts came from — «замеры прогонов» or «оценка»,
     *         because an estimate whose provenance is unstated is a number nobody can argue with
     */
    public function __construct(
        public int $terms,
        public int $coreTokensIn,
        public int $coreTokensOut,
        public int $mechanicsTokensIn,
        public int $mechanicsTokensOut,
        public ?string $coreUsd,
        public ?string $mechanicsUsd,
        public ?string $totalUsd,
        public ?string $totalBatchUsd,
        public string $source,
    ) {}
}
