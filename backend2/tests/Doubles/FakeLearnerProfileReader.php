<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class FakeLearnerProfileReader implements LearnerProfileReader
{
    public function __construct(private readonly CefrLevel $level = CefrLevel::B1) {}

    public function cefrLevelFor(UserId $user): CefrLevel
    {
        return $this->level;
    }
}
