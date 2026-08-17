<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

final readonly class BackfillSuppressionsOutcome
{
    /** @param  list<string>  $unmatched  a row whose TERM does not resolve — a typo, or content that moved */
    public function __construct(
        public int $inserted,
        public int $alreadySuppressed,
        public array $unmatched,
    ) {}
}
