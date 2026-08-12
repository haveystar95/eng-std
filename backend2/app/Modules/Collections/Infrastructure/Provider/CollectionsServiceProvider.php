<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Provider;

use App\Modules\Collections\Application\Port\CollectionsAccountEraser;
use App\Modules\Collections\Application\Port\CollectionSubscriptions;
use App\Modules\Collections\Application\Port\CollectionSyncReader;
use App\Modules\Collections\Application\Port\StoreCollectionsReader;
use App\Modules\Collections\Application\Port\StorePreviewReader;
use App\Modules\Collections\Application\Port\UserCollectionsReader;
use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Collections\Application\Query\PendingCollectionImageReader;
use App\Modules\Collections\Application\Port\CollectionCurator;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentCollectionCurator;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentCollectionRepository;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentCollectionsAccountEraser;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentCollectionSubscriptions;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentCollectionSyncReader;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentPendingCollectionImageReader;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentStoreCollectionsReader;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentStorePreviewReader;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentUserCollectionsReader;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentUserCollectionTermsReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CollectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CollectionRepository::class, EloquentCollectionRepository::class);
        $this->app->bind(CollectionCurator::class, EloquentCollectionCurator::class);
        $this->app->bind(UserCollectionsReader::class, EloquentUserCollectionsReader::class);
        $this->app->bind(UserCollectionTermsReader::class, EloquentUserCollectionTermsReader::class);
        $this->app->bind(CollectionSyncReader::class, EloquentCollectionSyncReader::class);
        $this->app->bind(PendingCollectionImageReader::class, EloquentPendingCollectionImageReader::class);
        $this->app->bind(StoreCollectionsReader::class, EloquentStoreCollectionsReader::class);
        $this->app->bind(StorePreviewReader::class, EloquentStorePreviewReader::class);
        $this->app->bind(CollectionSubscriptions::class, EloquentCollectionSubscriptions::class);
        $this->app->bind(CollectionsAccountEraser::class, EloquentCollectionsAccountEraser::class);
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
