<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Provider;

use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Infrastructure\SystemClock;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
    }
}
