<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class GetUsualVisitTime
{
    public function __construct(public UserId $userId) {}
}
