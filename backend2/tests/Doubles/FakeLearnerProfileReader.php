<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class FakeLearnerProfileReader implements LearnerProfileReader
{
    public function __construct(
        private readonly CefrLevel $level = CefrLevel::B1,
        private readonly int $newTermsPerDay = 20,
    ) {}

    public function cefrLevelFor(UserId $user): CefrLevel
    {
        return $this->level;
    }

    public function newTermsPerDay(UserId $user): int
    {
        return $this->newTermsPerDay;
    }
}
