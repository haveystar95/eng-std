<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One machine translation, with the thing the free plan is actually billed in beside it.
 *
 * `characters` is the length of what was SENT, not of what came back — that is how the vendor
 * counts, and a ledger that measured the reply would drift from the quota it exists to protect.
 */
final readonly class InstantTranslation
{
    public function __construct(
        public string $text,
        public string $provider,
        public int $characters,
    ) {}
}
