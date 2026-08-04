<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Read the caller's generation allowance (for the client to grey the button before submit). */
final readonly class GetGenerationQuota
{
    public function __construct(
        public UserId $userId,
    ) {}
}
