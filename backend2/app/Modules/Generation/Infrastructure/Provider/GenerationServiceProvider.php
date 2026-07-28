<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Provider;

use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\DispatchesGeneration;
use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\QueuedGenerationDispatcher;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationQuota;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationRequestRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class GenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GenerationRequestRepository::class, EloquentGenerationRequestRepository::class);
        $this->app->bind(GenerationQuota::class, EloquentGenerationQuota::class);
        $this->app->bind(DispatchesGeneration::class, QueuedGenerationDispatcher::class);

        $this->app->bind(CollectionGeneratorPort::class, function (): CollectionGeneratorPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeCollectionGenerator();
            }

            return new OpenAiCollectionGenerator(
                apiKey: (string) config('services.openai.api_key'),
                model: (string) config('services.openai.generate_model', 'gpt-4o'),
            );
        });
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
