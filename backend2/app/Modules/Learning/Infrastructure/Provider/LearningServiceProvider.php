<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Provider;

use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Domain\Repository\ReviewRepository;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Service\Fuzz;
use App\Modules\Learning\Domain\Service\Scheduler;
use App\Modules\Learning\Domain\Service\Sm2Scheduler;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentDailyStatsProjector;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentDueTermsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentReviewRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentStudySessionRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTermProgressRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermProgressRepository::class, EloquentTermProgressRepository::class);
        $this->app->bind(ReviewRepository::class, EloquentReviewRepository::class);
        $this->app->bind(StudySessionRepository::class, EloquentStudySessionRepository::class);
        $this->app->bind(DueTermsReader::class, EloquentDueTermsReader::class);
        $this->app->bind(StatsProjector::class, EloquentDailyStatsProjector::class);
        $this->app->bind(Scheduler::class, static fn (): Sm2Scheduler => new Sm2Scheduler(Fuzz::random()));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Migration');

        $routes = __DIR__ . '/../../Presentation/Http/routes.php';
        if (is_file($routes)) {
            Route::middleware('api')->prefix('api/v1')->group($routes);
        }
    }
}
