<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class RecordVisit
{
    public function __construct(public UserId $userId) {}
}
