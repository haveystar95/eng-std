<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The "New example" action: generate a fresh example for a term (counts against the daily quota). */
final readonly class RegenerateExample
{
    public function __construct(
        public UserId $userId,
        public TermId $termId,
    ) {}
}
