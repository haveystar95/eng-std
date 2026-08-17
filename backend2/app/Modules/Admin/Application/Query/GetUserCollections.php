<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** A user's collections with per-collection progress counters. */
final readonly class GetUserCollections
{
    public function __construct(public UserId $userId) {}
}
