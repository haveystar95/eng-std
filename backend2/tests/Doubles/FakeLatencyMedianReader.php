<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Port\LatencyMedianReader;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LatencyBaseline;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class FakeLatencyMedianReader implements LatencyMedianReader
{
    public int $forgotten = 0;

    public function __construct(private readonly ?LatencyBaseline $baseline = null) {}

    public function medianFor(UserId $user, ExerciseMode $mode): LatencyBaseline
    {
        return $this->baseline ?? LatencyBaseline::insufficient();
    }

    public function forget(UserId $user, ExerciseMode $mode): void
    {
        $this->forgotten++;
    }
}
