<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Provider;

use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Collections\Infrastructure\Eloquent\EloquentCollectionRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CollectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CollectionRepository::class, EloquentCollectionRepository::class);
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
