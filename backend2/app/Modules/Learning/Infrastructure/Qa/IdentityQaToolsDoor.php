<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Qa;

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Learning\Application\Port\QaToolsDoor;
use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class IdentityQaToolsDoor implements QaToolsDoor
{
    public function __construct(private UserReader $users) {}

    public function isOpenFor(UserId $user): bool
    {
        // ОБА ЗАМКА уже сложены в одном месте — `qa_tools` пользователя (Identity).
        return $this->users->byId($user)?->qaTools === true;
    }
}
