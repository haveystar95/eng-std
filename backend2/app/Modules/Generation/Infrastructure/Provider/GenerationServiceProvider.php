<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Provider;

use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\DispatchesGeneration;
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Port\GenerationAccountEraser;
use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\RecordsTermEnrichment;
use App\Modules\Generation\Application\Port\TermEnricherPort;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\PexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\FakeTermEnricher;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiTermEnricher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedGenerationDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedImageAttachmentDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedTermEnrichmentDispatcher;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationAccountEraser;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationQuota;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationRequestRepository;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentTermEnrichmentLog;
use App\Modules\Vocabulary\Application\Port\DispatchesTermEnrichment;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class GenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GenerationRequestRepository::class, EloquentGenerationRequestRepository::class);
        $this->app->bind(GenerationQuota::class, EloquentGenerationQuota::class);
        $this->app->bind(GenerationAccountEraser::class, EloquentGenerationAccountEraser::class);
        $this->app->bind(RecordsTermEnrichment::class, EloquentTermEnrichmentLog::class);
        $this->app->bind(DispatchesGeneration::class, QueuedGenerationDispatcher::class);
        $this->app->bind(DispatchesImageAttachment::class, QueuedImageAttachmentDispatcher::class);
        // Fulfils Vocabulary's enrichment-dispatch port with the Generation queue job.
        $this->app->bind(DispatchesTermEnrichment::class, QueuedTermEnrichmentDispatcher::class);

        $this->app->bind(TermEnricherPort::class, function (): TermEnricherPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeTermEnricher();
            }

            return new OpenAiTermEnricher(
                apiKey: (string) config('services.openai.api_key'),
                model: (string) config('services.openai.enrich_model', 'gpt-4o-mini'),
            );
        });

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

            return new PexelsImageSearch(
                apiKey: (string) config('services.pexels.key'),
                throttleMs: (int) config('services.pexels.throttle_ms', 0),
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
