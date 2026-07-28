<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

final class FakeGenerationQuota implements GenerationQuota
{
    public function __construct(private readonly int $used = 0) {}

    public function usedOn(UserId $userId, DateTimeImmutable $day): int
    {
        return $this->used;
    }
}
