<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One structured answer from one provider, with everything a comparison needs beside it.
 *
 * The measurements are part of the answer rather than something a caller times from outside,
 * because "how long did the model take" and "how long did my loop take" are different numbers and
 * only the first one belongs in a bake-off table.
 */
final readonly class ModelAnswer
{
    /**
     * @param  array<string, mixed>  $payload  the decoded JSON object, already validated against the
     *                                         requested schema by the provider (or by the adapter,
     *                                         where the provider cannot enforce one)
     * @param  string  $model  what the provider says it used, which is not always what we asked for
     * @param  int  $latencyMs  wall time of the vendor call alone
     * @param  string|null  $costUsd  priced from the token counts, or null when the model is not in
     *                                the pricing table — an unpriced call is reported as unpriced,
     *                                never as free
     * @param  string  $raw  the answer text, truncated — enough to diagnose a malformed reply
     */
    public function __construct(
        public array $payload,
        public string $model,
        public int $latencyMs,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
        public string $raw = '',
    ) {}
}
