<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Provider;

use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\DispatchesGeneration;
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Port\ExampleRegeneratorPort;
use App\Modules\Generation\Application\Port\GenerationAccountEraser;
use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\DialogSummarizerPort;
use App\Modules\Generation\Application\Port\PracticeQuota;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Generation\Application\Port\RecordsExampleRegeneration;
use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Application\Port\RecordsTermEnrichment;
use App\Modules\Generation\Application\Port\TermEnricherPort;
use App\Modules\Generation\Application\Dto\PracticeDialogConfig;
use App\Modules\Generation\Application\Dto\RealtimeVad;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Domain\Repository\PracticeDialogMessageRepository;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;
use App\Modules\Generation\Domain\Service\PracticeDailyLimit;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\PexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\FakeExampleRegenerator;
use App\Modules\Generation\Infrastructure\Adapter\FakeEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Adapter\FakeTermEnricher;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiExampleRegenerator;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiTermEnricher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedEnrichmentDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedGenerationDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedImageAttachmentDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedTermEnrichmentDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\FakeDialogSummarizer;
use App\Modules\Generation\Infrastructure\Adapter\FakeRealtimeTokenMinter;
use App\Modules\Generation\Infrastructure\Adapter\GeminiLiveTokenMinter;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiDialogSummarizer;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiRealtimeTokenMinter;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentEnrichmentJournal;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationAccountEraser;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationQuota;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentExampleRegenerationLog;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationRequestRepository;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentPracticeDialogMessageRepository;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentPracticeDialogRepository;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentPracticeQuota;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentTermEnrichmentLog;
use App\Modules\Generation\Infrastructure\Prompt\PracticeDialogInstructions;
use App\Modules\Shared\Domain\Service\Clock;
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
        $this->app->bind(RecordsExampleRegeneration::class, EloquentExampleRegenerationLog::class);
        $this->app->bind(DispatchesGeneration::class, QueuedGenerationDispatcher::class);
        $this->app->bind(DispatchesImageAttachment::class, QueuedImageAttachmentDispatcher::class);
        // Fulfils Vocabulary's enrichment-dispatch port with the Generation queue job.
        $this->app->bind(DispatchesTermEnrichment::class, QueuedTermEnrichmentDispatcher::class);
        // The enrichment станок: its own bookkeeping (marks + findings) and its own queue entry.
        $this->app->bind(EnrichmentJournal::class, EloquentEnrichmentJournal::class);
        $this->app->bind(DispatchesEnrichment::class, QueuedEnrichmentDispatcher::class);

        $this->app->bind(EnrichmentPackerPort::class, function (): EnrichmentPackerPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeEnrichmentPacker();
            }

            return new OpenAiEnrichmentPacker(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                // The станок reasons about grammar rather than recalling facts, and it runs over
                // hundreds of terms — the cheaper model is the wrong trade here only if the scrap
                // rate says so, which is exactly what the run metrics measure.
                model: (string) config('services.openai.enrich_model', 'gpt-4o-mini'),
                promptVersion: (string) config('services.generation.enrich_pack_prompt_version', 'v1'),
            );
        });

        $this->app->bind(TermEnricherPort::class, function (): TermEnricherPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeTermEnricher();
            }

            return new OpenAiTermEnricher(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                model: (string) config('services.openai.enrich_model', 'gpt-4o-mini'),
            );
        });

        $this->app->bind(ExampleRegeneratorPort::class, function (): ExampleRegeneratorPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeExampleRegenerator();
            }

            return new OpenAiExampleRegenerator(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                model: (string) config('services.openai.enrich_model', 'gpt-4o-mini'),
            );
        });

        $this->app->bind(CollectionGeneratorPort::class, function (): CollectionGeneratorPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeCollectionGenerator();
            }

            return new OpenAiCollectionGenerator(
                context: $this->app->make(OutboundCallContext::class),
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
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.pexels.key'),
                throttleMs: (int) config('services.pexels.throttle_ms', 0),
            );
        });

        // ---- Realtime practice dialogs -------------------------------------------------------
        $this->app->bind(PracticeDialogRepository::class, EloquentPracticeDialogRepository::class);
        $this->app->bind(PracticeDialogMessageRepository::class, EloquentPracticeDialogMessageRepository::class);
        $this->app->bind(PracticeQuota::class, EloquentPracticeQuota::class);

        $this->app->singleton(PracticeDailyLimit::class, fn (): PracticeDailyLimit => new PracticeDailyLimit(
            (int) config('services.practice.dialogs_per_day', PracticeDailyLimit::DEFAULT_PER_DAY),
        ));

        $this->app->singleton(PracticeDialogConfig::class, function (): PracticeDialogConfig {
            // The active realtime model depends on the driver — resolved once, here, so the
            // Application layer stays provider-agnostic (the lesson just carries "the model").
            $model = config('services.practice.driver') === 'gemini'
                ? (string) config('services.practice.gemini_model', 'gemini-3.1-flash-live-preview')
                : (string) config('services.practice.realtime_model', 'gpt-realtime-2.1-mini');

            return new PracticeDialogConfig(
                realtimeModel: $model,
                transcribeModel: (string) config('services.practice.transcribe_model', 'gpt-4o-mini-transcribe'),
                voice: (string) config('services.practice.voice', 'alloy'),
                ttlSeconds: (int) config('services.practice.dialog_ttl_seconds', 200),
                maxTargetWords: (int) config('services.practice.max_target_words', 8),
                vad: new RealtimeVad(
                    silenceMs: (int) config('services.practice.vad_silence_ms', 900),
                    threshold: (float) config('services.practice.vad_threshold', 0.5),
                    prefixPaddingMs: (int) config('services.practice.vad_prefix_padding_ms', 300),
                ),
                slowSpeed: (float) config('services.practice.slow_speed', 0.9),
            );
        });

        $this->app->bind(RealtimeTokenPort::class, function (): RealtimeTokenPort {
            $driver = config('services.practice.driver');

            if ($driver === 'fake') {
                return new FakeRealtimeTokenMinter($this->app->make(Clock::class));
            }

            if ($driver === 'gemini') {
                return new GeminiLiveTokenMinter(
                    context: $this->app->make(OutboundCallContext::class),
                    apiKey: (string) config('services.gemini.api_key'),
                    instructions: $this->app->make(PracticeDialogInstructions::class),
                    clock: $this->app->make(Clock::class),
                    promptVersion: (string) config('services.practice.prompt_version', 'v3'),
                    constrained: (bool) config('services.practice.gemini_constrained', false),
                );
            }

            return new OpenAiRealtimeTokenMinter(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                instructions: $this->app->make(PracticeDialogInstructions::class),
                promptVersion: (string) config('services.practice.prompt_version', 'v3'),
            );
        });

        $this->app->bind(DialogSummarizerPort::class, function (): DialogSummarizerPort {
            if (config('services.practice.driver') === 'fake') {
                return new FakeDialogSummarizer();
            }

            return new OpenAiDialogSummarizer(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                model: (string) config('services.openai.summary_model', 'gpt-4o-mini'),
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
