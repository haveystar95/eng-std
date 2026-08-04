<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * The outcome of the generation pipeline: the final accepted draft (after overshoot-trim and an
 * optional top-up) plus the spend summed across every model call. `delivered` may be below the
 * requested size — an honest under-delivery, not a failure. `primaryRaw` is the untrimmed first
 * response, kept so the eval tool can measure raw vs delivered and the model's own duplicate rate.
 */
final readonly class AssembledDraft
{
    public function __construct(
        public GeneratedCollectionDraft $draft,
        public GeneratedCollectionDraft $primaryRaw,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
        public int $delivered,
    ) {}
}
