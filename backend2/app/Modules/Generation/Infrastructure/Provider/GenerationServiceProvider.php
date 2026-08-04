<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Provider;

use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\DispatchesGeneration;
use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\PexelsImageSearch;
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
                // Which prompt file to load. Defaults to the production version so the recorded
                // prompt_version and the file used always match; the eval command overrides it via
                // config to trial a new version (e.g. v3) without flipping production.
                promptVersion: (string) config('services.generation.prompt_version', RequestCollectionGenerationHandler::PROMPT_VERSION),
            );
        });

        $this->app->bind(ImageSearchPort::class, function (): ImageSearchPort {
            if (config('services.generation.image_driver') === 'fake') {
                return new FakePexelsImageSearch((string) config('services.pexels.fake_mode', 'found'));
            }

            return new PexelsImageSearch(apiKey: (string) config('services.pexels.key'));
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
