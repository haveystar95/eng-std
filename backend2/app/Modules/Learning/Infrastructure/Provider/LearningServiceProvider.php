<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Provider;

use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Port\LearningAccountEraser;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Learning\Application\Port\LatencyMedianReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Application\Port\SessionContextReader;
use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Application\Port\StatsReader;
use App\Modules\Learning\Application\Port\ProgressSyncReader;
use App\Modules\Learning\Application\Port\SyncCursorReader;
use App\Modules\Learning\Application\Port\TriagedTermsReader;
use App\Modules\Learning\Application\Port\TriageSyncReader;
use App\Modules\Learning\Domain\Repository\ReviewRepository;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Repository\TriageRepository;
use App\Modules\Learning\Domain\Service\Fuzz;
use App\Modules\Learning\Domain\Service\Scheduler;
use App\Modules\Learning\Domain\Service\Sm2Scheduler;
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Application\Port\ModeFallbackReporter;
use App\Modules\Learning\Infrastructure\Adapter\LoggingModeFallbackReporter;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentEnabledModesReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentEnabledModesWriter;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentDailyStatsProjector;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentDueTermsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentIntroducedTermsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentLearningAccountEraser;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentProgressExistenceReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentProgressSnapshotReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentProgressSyncReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentReviewRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentSessionContextReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentStatsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentSyncCursorReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentStudySessionRepository;
use App\Modules\Learning\Infrastructure\Adapter\IdentityLearnerProfileReader;
use App\Modules\Learning\Infrastructure\Eloquent\CachedLatencyMedianReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTermProgressRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTriagedTermsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTriageRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTriageSyncReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermProgressRepository::class, EloquentTermProgressRepository::class);
        $this->app->bind(ReviewRepository::class, EloquentReviewRepository::class);
        $this->app->bind(TriageRepository::class, EloquentTriageRepository::class);
        $this->app->bind(TriagedTermsReader::class, EloquentTriagedTermsReader::class);
        $this->app->bind(TriageSyncReader::class, EloquentTriageSyncReader::class);
        $this->app->bind(SyncCursorReader::class, EloquentSyncCursorReader::class);
        $this->app->bind(ProgressSyncReader::class, EloquentProgressSyncReader::class);
        $this->app->bind(LearnerProfileReader::class, IdentityLearnerProfileReader::class);
        $this->app->bind(LatencyMedianReader::class, CachedLatencyMedianReader::class);
        $this->app->bind(SessionContextReader::class, EloquentSessionContextReader::class);
        $this->app->bind(StudySessionRepository::class, EloquentStudySessionRepository::class);
        $this->app->bind(DueTermsReader::class, EloquentDueTermsReader::class);
        $this->app->bind(ProgressExistenceReader::class, EloquentProgressExistenceReader::class);
        $this->app->bind(ProgressSnapshotReader::class, EloquentProgressSnapshotReader::class);
        $this->app->bind(IntroducedTermsReader::class, EloquentIntroducedTermsReader::class);
        $this->app->bind(StatsProjector::class, EloquentDailyStatsProjector::class);
        $this->app->bind(StatsReader::class, EloquentStatsReader::class);
        $this->app->bind(LearningAccountEraser::class, EloquentLearningAccountEraser::class);
        $this->app->bind(Scheduler::class, static fn (): Sm2Scheduler => new Sm2Scheduler(Fuzz::random()));

        // The mode set is DATA now (learning_mode_settings), not a compile-time value, because it
        // depends on who is asking — so it is a reader, singleton only for its per-request memo.
        // `config/learning.php` survives as the seed for the global row and as the emergency
        // fallback if that row is ever deleted; nothing reads it on the hot path.
        // Singleton on the CONCRETE class, with the port aliased to it: bound the other way round,
        // anything that asks for the concrete reader (the writer, to invalidate the memo) would get
        // a second instance and the invalidation would land on nobody.
        $this->app->singleton(EloquentEnabledModesReader::class);
        $this->app->alias(EloquentEnabledModesReader::class, EnabledModesReader::class);
        $this->app->bind(EnabledModesWriter::class, EloquentEnabledModesWriter::class);
        $this->app->bind(ModeFallbackReporter::class, LoggingModeFallbackReporter::class);
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
