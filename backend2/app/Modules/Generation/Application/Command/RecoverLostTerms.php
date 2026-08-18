<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

final readonly class RecoverLostTerms
{
    public function __construct(
        public bool $apply = false,
    ) {}
}
