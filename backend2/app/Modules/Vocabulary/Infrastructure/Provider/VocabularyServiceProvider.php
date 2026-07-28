<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Provider;

use App\Modules\Vocabulary\Application\Query\TermContentReader;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;
use App\Modules\Vocabulary\Domain\Repository\TermRepository;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermContentReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermExistenceReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class VocabularyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermRepository::class, EloquentTermRepository::class);
        $this->app->bind(TermExistenceReader::class, EloquentTermExistenceReader::class);
        $this->app->bind(TermContentReader::class, EloquentTermContentReader::class);
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
