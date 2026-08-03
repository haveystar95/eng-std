<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Provider;

use App\Modules\Collections\Application\Port\CollectionSyncReader;
use App\Modules\Collections\Application\Port\UserCollectionsReader;
use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentCollectionRepository;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentCollectionSyncReader;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentUserCollectionsReader;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentUserCollectionTermsReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CollectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CollectionRepository::class, EloquentCollectionRepository::class);
        $this->app->bind(UserCollectionsReader::class, EloquentUserCollectionsReader::class);
        $this->app->bind(UserCollectionTermsReader::class, EloquentUserCollectionTermsReader::class);
        $this->app->bind(CollectionSyncReader::class, EloquentCollectionSyncReader::class);
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
