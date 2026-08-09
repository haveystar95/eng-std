<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Provider;

use App\Modules\Admin\Application\Port\AdminAuditRecorder;
use App\Modules\Admin\Application\Port\AdminCollectionReader;
use App\Modules\Admin\Application\Port\AdminCostReader;
use App\Modules\Admin\Application\Port\AdminDialogReader;
use App\Modules\Admin\Application\Port\AdminGenerationReader;
use App\Modules\Admin\Application\Port\AdminLogin;
use App\Modules\Admin\Application\Port\AdminMetricsReader;
use App\Modules\Admin\Application\Port\AdminReader;
use App\Modules\Admin\Application\Port\AdminRegistrar;
use App\Modules\Admin\Application\Port\AdminRequestLogReader;
use App\Modules\Admin\Application\Port\AdminReviewReader;
use App\Modules\Admin\Application\Port\AdminSignOut;
use App\Modules\Admin\Application\Port\AdminTermReader;
use App\Modules\Admin\Application\Port\AdminUserReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminAuditRecorder;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminCollectionReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminCostReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminDialogReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminGenerationReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminLogin;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminMetricsReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminRegistrar;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminRequestLogReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminReviewReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminTermReader;
use App\Modules\Admin\Infrastructure\Eloquent\EloquentAdminUserReader;
use App\Modules\Admin\Infrastructure\Eloquent\SanctumAdminSignOut;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdminLogin::class, EloquentAdminLogin::class);
        $this->app->bind(AdminRegistrar::class, EloquentAdminRegistrar::class);
        $this->app->bind(AdminReader::class, EloquentAdminReader::class);
        $this->app->bind(AdminSignOut::class, SanctumAdminSignOut::class);
        $this->app->bind(AdminAuditRecorder::class, EloquentAdminAuditRecorder::class);

        $this->app->bind(AdminMetricsReader::class, EloquentAdminMetricsReader::class);
        $this->app->bind(AdminCostReader::class, EloquentAdminCostReader::class);
        $this->app->bind(AdminUserReader::class, EloquentAdminUserReader::class);
        $this->app->bind(AdminReviewReader::class, EloquentAdminReviewReader::class);
        $this->app->bind(AdminCollectionReader::class, EloquentAdminCollectionReader::class);
        $this->app->bind(AdminTermReader::class, EloquentAdminTermReader::class);
        $this->app->bind(AdminRequestLogReader::class, EloquentAdminRequestLogReader::class);
        $this->app->bind(AdminDialogReader::class, EloquentAdminDialogReader::class);
        $this->app->bind(AdminGenerationReader::class, EloquentAdminGenerationReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Migration');

        $routes = __DIR__ . '/../../Presentation/Http/routes.php';
        if (is_file($routes)) {
            Route::middleware('api')->prefix('admin/api')->group($routes);
        }
    }
}
