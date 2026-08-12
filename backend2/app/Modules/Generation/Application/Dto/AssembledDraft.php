<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\RejectedItem;

/**
 * The outcome of the generation pipeline: the final accepted draft (after overshoot-trim, the
 * language barrier and an optional top-up) plus the spend summed across every model call, repairs
 * included. `delivered` may be below the requested size — an honest under-delivery, not a failure.
 * `primaryRaw` is the untrimmed first response, kept so the eval tool can measure raw vs delivered
 * and the model's own duplicate rate.
 *
 * `rejections` is what the barrier refused to write. It is carried out of the pipeline rather than
 * logged inside it because the pipeline has no request id and no database — and because the eval
 * tool needs the same number the production run produces.
 */
final readonly class AssembledDraft
{
    /** @param list<RejectedItem> $rejections */
    public function __construct(
        public GeneratedCollectionDraft $draft,
        public GeneratedCollectionDraft $primaryRaw,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
        public int $delivered,
        public array $rejections = [],
    ) {}
}
