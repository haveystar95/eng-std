<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\Service\Clock;
use DateTimeImmutable;

final class FixedClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
