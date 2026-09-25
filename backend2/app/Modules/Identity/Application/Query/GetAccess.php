<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** The learner's access now (наряд ACC-1 §2): the free plan or the paid one, until when, by which right. */
final readonly class GetAccess
{
    public function __construct(public UserId $userId) {}
}
