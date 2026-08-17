<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * Spend split by purpose over some scope — one collection, or the whole fleet over a window.
 *
 * `note` carries the caveats that a bare number would hide (a shared term's enrichment is counted
 * in every collection that holds it). Unit economics you can't interrogate are unit economics you
 * shouldn't trust.
 */
final readonly class CostByPurposeView
{
    /** @param list<PurposeCost> $byPurpose */
    public function __construct(
        public array $byPurpose,
        public float $totalUsd,
        public int $tokensIn,
        public int $tokensOut,
        public ?string $scopeId = null,
        public ?string $period = null,
        public ?string $since = null,
        public ?string $note = null,
    ) {}

    /** @param list<PurposeCost> $parts */
    public static function of(array $parts, ?string $scopeId = null, ?string $period = null, ?string $since = null, ?string $note = null): self
    {
        $total = 0.0;
        $tokensIn = 0;
        $tokensOut = 0;
        foreach ($parts as $p) {
            $total += $p->costUsd;
            $tokensIn += $p->tokensIn;
            $tokensOut += $p->tokensOut;
        }

        return new self($parts, round($total, 6), $tokensIn, $tokensOut, $scopeId, $period, $since, $note);
    }
}
