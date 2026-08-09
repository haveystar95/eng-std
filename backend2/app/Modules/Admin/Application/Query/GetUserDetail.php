<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class GetUserDetail
{
    public function __construct(public UserId $userId) {}
}
