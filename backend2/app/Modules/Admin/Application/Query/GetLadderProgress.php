<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\AdminLadderFilter;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class GetLadderProgress
{
    public function __construct(
        public UserId $userId,
        public AdminLadderFilter $filter,
        public ListWindow $window,
    ) {}
}
