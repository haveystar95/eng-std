<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Provider;

use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\BakeoffJournal;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\PlaygroundModelCatalog;
use App\Modules\Generation\Application\Port\PromptSource;
use App\Modules\Generation\Application\Service\ContentContract;
use App\Modules\Generation\Application\Service\VocabularyKeyIsomorphism;
use App\Modules\Generation\Domain\Service\KeyIsomorphism;
use App\Modules\Generation\Application\Port\DispatchesGeneration;
use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Port\ExampleRegeneratorPort;
use App\Modules\Generation\Application\Port\GenerationAccountEraser;
use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\LoggedResponseReader;
use App\Modules\Generation\Application\Port\ObservedTokenAverages;
use App\Modules\Generation\Application\Port\DialogSummarizerPort;
use App\Modules\Generation\Application\Port\PracticeQuota;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Generation\Application\Port\InstantTranslationCache;
use App\Modules\Generation\Application\Port\SearchLookupCache;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Domain\Service\TranslationMonthlyBudget;
use App\Modules\Generation\Infrastructure\Adapter\DeepLTranslator;
use App\Modules\Generation\Infrastructure\Adapter\FakeTranslator;
use App\Modules\Generation\Infrastructure\Adapter\UnavailableTranslator;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentInstantTranslationCache;
use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Application\Command\AddSearchResultHandler;
use App\Modules\Generation\Domain\Service\SearchLookupDailyLimit;
use App\Modules\Generation\Domain\Service\SearchQueryLength;
use App\Modules\Generation\Infrastructure\Adapter\FakeWordLookup;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiWordLookup;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentSearchLookupCache;
use App\Modules\Generation\Application\Port\RecordsExampleRegeneration;
use App\Modules\Generation\Application\Port\RecordsGenerationRejections;
use App\Modules\Generation\Application\Port\TranslationRepairPort;
use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Application\Port\RecordsTermEnrichment;
use App\Modules\Generation\Application\Port\TermEnricherPort;
use App\Modules\Generation\Application\Dto\GenerationStackConfig;
use App\Modules\Generation\Application\Dto\PracticeDialogConfig;
use App\Modules\Generation\Application\Dto\RealtimeVad;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Domain\Repository\PracticeDialogMessageRepository;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;
use App\Modules\Generation\Domain\Service\PracticeDailyLimit;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Adapter\ConfiguredContentModelCatalog;
use App\Modules\Generation\Infrastructure\Adapter\ConfiguredPlaygroundCatalog;
use App\Modules\Generation\Infrastructure\Adapter\ContentModelCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\MachineryEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\ObservabilityLoggedResponseReader;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\PexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\FakeExampleRegenerator;
use App\Modules\Generation\Infrastructure\Adapter\FakeEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Adapter\FakeTermEnricher;
use App\Modules\Generation\Infrastructure\Adapter\FakeTranslationRepairer;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiTranslationRepairer;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiExampleRegenerator;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiTermEnricher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedEnrichmentDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedGenerationDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedExampleRepairDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedImageAttachmentDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\QueuedTermEnrichmentDispatcher;
use App\Modules\Generation\Infrastructure\Adapter\FakeDialogSummarizer;
use App\Modules\Generation\Infrastructure\Adapter\FakeRealtimeTokenMinter;
use App\Modules\Generation\Infrastructure\Adapter\GeminiLiveTokenMinter;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiDialogSummarizer;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiRealtimeTokenMinter;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentBakeoffJournal;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentEnrichmentJournal;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationAccountEraser;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationQuota;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentExampleRegenerationLog;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationRejectionJournal;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationRequestRepository;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentObservedTokenAverages;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentPracticeDialogMessageRepository;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentPracticeDialogRepository;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentPracticeQuota;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentTermEnrichmentLog;
use App\Modules\Generation\Infrastructure\Prompt\PracticeDialogInstructions;
use App\Modules\Generation\Infrastructure\Prompt\PromptLibrary;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Vocabulary\Application\Port\DispatchesTermEnrichment;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Illuminate\Support\ServiceProvider;

final class GenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GenerationRequestRepository::class, EloquentGenerationRequestRepository::class);
        // The multi-vendor seam. Bound unconditionally: which providers can actually be called is a
        // runtime fact about keys, which the catalogue reports rather than a driver switch decides.
        $this->app->bind(ContentModelCatalog::class, ConfiguredContentModelCatalog::class);
        // The admin sandbox's own registry. A SECOND catalogue beside the one above, not a widening
        // of it: this one hands out adapters that send no system prompt and demand no schema, which
        // is exactly what nothing on the production path may ever get.
        $this->app->bind(PlaygroundModelCatalog::class, ConfiguredPlaygroundCatalog::class);
        // Prompts are files, so reading them is Infrastructure's job; deciding which version to run
        // is Application's. The port is what keeps that direction one-way.
        $this->app->bind(PromptSource::class, PromptLibrary::class);
        // One definition of a broken translation key in the codebase: Vocabulary's. Generation
        // declares the collaborator it needs and this is what fulfils it.
        $this->app->bind(KeyIsomorphism::class, VocabularyKeyIsomorphism::class);
        // The bake-off's sandbox. Bound here so nothing else can be handed a writer by accident.
        $this->app->bind(BakeoffJournal::class, EloquentBakeoffJournal::class);
        $this->app->bind(LoggedResponseReader::class, ObservabilityLoggedResponseReader::class);
        // What calls on a model have really cost in tokens — the measured half of a spend estimate.
        $this->app->bind(ObservedTokenAverages::class, EloquentObservedTokenAverages::class);
        $this->app->bind(GenerationQuota::class, EloquentGenerationQuota::class);
        $this->app->bind(GenerationAccountEraser::class, EloquentGenerationAccountEraser::class);
        $this->app->bind(RecordsTermEnrichment::class, EloquentTermEnrichmentLog::class);
        $this->app->bind(RecordsExampleRegeneration::class, EloquentExampleRegenerationLog::class);
        // The language barrier's log: which items a generation refused to write, and why.
        $this->app->bind(RecordsGenerationRejections::class, EloquentGenerationRejectionJournal::class);
        $this->app->bind(DispatchesGeneration::class, QueuedGenerationDispatcher::class);
        $this->app->bind(DispatchesImageAttachment::class, QueuedImageAttachmentDispatcher::class);
        $this->app->bind(DispatchesExampleRepair::class, QueuedExampleRepairDispatcher::class);
        // Fulfils Vocabulary's enrichment-dispatch port with the Generation queue job.
        $this->app->bind(DispatchesTermEnrichment::class, QueuedTermEnrichmentDispatcher::class);
        // The enrichment станок: its own bookkeeping (marks + findings) and its own queue entry.
        $this->app->bind(EnrichmentJournal::class, EloquentEnrichmentJournal::class);
        $this->app->bind(DispatchesEnrichment::class, QueuedEnrichmentDispatcher::class);

        $this->app->bind(EnrichmentPackerPort::class, function (): EnrichmentPackerPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeEnrichmentPacker();
            }

            $stack = $this->app->make(GenerationStackConfig::class);

            if (! $stack->isLegacy()) {
                $model = $this->app->make(ContentModelCatalog::class)
                    ->get($stack->mechanicsProvider, $stack->mechanicsModel);

                if ($model === null) {
                    throw new RuntimeException(
                        "The станок is configured on provider «{$stack->mechanicsProvider->value}», "
                        . 'which has no API key. Set the key, or roll back with GENERATION_STACK=v1.'
                    );
                }

                return new MachineryEnrichmentPacker(
                    model: $model,
                    prompts: $this->app->make(PromptSource::class),
                    contract: $this->app->make(ContentContract::class),
                    promptVersion: $stack->mechanicsPromptVersion,
                );
            }

            return new OpenAiEnrichmentPacker(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                // The станок reasons about grammar rather than recalling facts, and it runs over
                // hundreds of terms — the cheaper model is the wrong trade here only if the scrap
                // rate says so, which is exactly what the run metrics measure.
                model: (string) config('services.openai.enrich_model', 'gpt-4o-mini'),
                promptVersion: (string) config('services.generation.enrich_pack_prompt_version', 'v2'),
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

        // ---- instant translation (the search field's grey line) ------------------------------
        $this->app->bind(InstantTranslationCache::class, EloquentInstantTranslationCache::class);

        $this->app->bind(TranslationProvider::class, function (): TranslationProvider {
            if (config('services.deepl.driver') === 'fake') {
                return new FakeTranslator();
            }

            $key = (string) config('services.deepl.api_key', '');
            // A NULL OBJECT and not a null binding: «no key» is a state the endpoint reports, and
            // a deployment without one must behave, not break. See UnavailableTranslator.
            if (trim($key) === '') {
                return new UnavailableTranslator();
            }

            return new DeepLTranslator(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: $key,
                baseUrl: (string) config('services.deepl.base_url', 'https://api-free.deepl.com/v2'),
            );
        });

        $this->app->bind(TranslationMonthlyBudget::class, fn (): TranslationMonthlyBudget => new TranslationMonthlyBudget(
            (int) config('services.deepl.monthly_characters', TranslationMonthlyBudget::FREE_PLAN_CHARACTERS),
        ));

        // ---- word search -------------------------------------------------------------------
        $this->app->bind(SearchLookupCache::class, EloquentSearchLookupCache::class);

        $this->app->bind(WordLookupPort::class, function (): WordLookupPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeWordLookup();
            }

            return new OpenAiWordLookup(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                model: (string) config('services.openai.search_model', 'gpt-4o-mini'),
            );
        });

        // The cap is a value, resolved once from config, so the handler that enforces it and the
        // response that reports it cannot read two different numbers.
        $this->app->bind(SearchLookupDailyLimit::class, fn (): SearchLookupDailyLimit => new SearchLookupDailyLimit(
            (int) config('services.generation.search_lookup_daily_cap', SearchLookupDailyLimit::DEFAULT_CAP),
        ));

        // Same reason: the instant hint and the paid lookup both refuse a paragraph, and they have
        // to refuse the SAME paragraph — one number, read in one place.
        $this->app->bind(SearchQueryLength::class, fn (): SearchQueryLength => new SearchQueryLength(
            (int) config('services.generation.search_query_max_chars', SearchQueryLength::DEFAULT_MAX),
        ));

        // Saving a searched word chains the станок exactly as a finished generation does — same
        // switch, read here rather than in the handler so Application stays clear of config().
        $this->app->when(AddSearchResultHandler::class)
            ->needs('$autoEnrich')
            ->give(fn (): bool => config('services.generation.auto_enrich') === true);

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

        // Which stack production generates on. Resolved once, from config, and injected — so the
        // handler that records `prompt_version` and the adapter that renders the prompt cannot
        // disagree about which version is live (they used to share a constant, which only worked
        // while there was one stack).
        $this->app->singleton(GenerationStackConfig::class, function (): GenerationStackConfig {
            $stack = config('services.generation.stack') === GenerationStackConfig::LEGACY
                ? GenerationStackConfig::LEGACY
                : GenerationStackConfig::CONTENT_MODEL;

            return new GenerationStackConfig(
                stack: $stack,
                // The v1 stack's prompt version is frozen with the adapter that loads it; the v2
                // stack's is configurable, and `prompt_version` stays the eval command's override
                // for either — that is what lets a new version be trialled without flipping prod.
                corePromptVersion: (string) config(
                    'services.generation.prompt_version',
                    $stack === GenerationStackConfig::LEGACY
                        ? RequestCollectionGenerationHandler::PROMPT_VERSION
                        : (string) config('services.generation.core_prompt_version', 'v11.1'),
                ),
                coreProvider: ProviderId::tryFrom((string) config('services.generation.core_provider', 'openai')) ?? ProviderId::OpenAi,
                coreModel: (string) config('services.generation.core_model', 'gpt-5.4'),
                mechanicsPromptVersion: (string) config('services.generation.mechanics_prompt_version', 'v12.1'),
                mechanicsProvider: ProviderId::tryFrom((string) config('services.generation.mechanics_provider', 'openai')) ?? ProviderId::OpenAi,
                mechanicsModel: (string) config('services.generation.mechanics_model', 'gpt-4o-mini'),
            );
        });

        $this->app->bind(CollectionGeneratorPort::class, function (): CollectionGeneratorPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeCollectionGenerator();
            }

            $stack = $this->app->make(GenerationStackConfig::class);

            if (! $stack->isLegacy()) {
                $model = $this->app->make(ContentModelCatalog::class)
                    ->get($stack->coreProvider, $stack->coreModel);

                // No key for the configured provider is a misconfiguration, not a reason to
                // silently generate on the old stack with a different model and a different
                // prompt: a caller that asked for v2 and got v9 content would have no way to see
                // it. Fall back only when the provider is genuinely unreachable — and say so.
                if ($model === null) {
                    throw new RuntimeException(
                        "Generation stack v2 is configured on provider «{$stack->coreProvider->value}», "
                        . 'which has no API key. Set the key, or roll back with GENERATION_STACK=v1.'
                    );
                }

                return new ContentModelCollectionGenerator(
                    model: $model,
                    prompts: $this->app->make(PromptSource::class),
                    contract: $this->app->make(ContentContract::class),
                    promptVersion: $stack->corePromptVersion,
                );
            }

            return new OpenAiCollectionGenerator(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                model: (string) config('services.openai.generate_model', 'gpt-4o'),
                // Which prompt file to load. Defaults to the production version so the recorded
                // prompt_version and the file used always match; the eval command overrides it via
                // config to trial a new version (e.g. v3) without flipping production.
                promptVersion: $stack->corePromptVersion,
            );
        });

        $this->app->bind(TranslationRepairPort::class, function (): TranslationRepairPort {
            if (config('services.generation.driver') === 'fake') {
                return new FakeTranslationRepairer();
            }

            return new OpenAiTranslationRepairer(
                context: $this->app->make(OutboundCallContext::class),
                apiKey: (string) config('services.openai.api_key'),
                // Translating one short string that has already been chosen is not the job the
                // expensive model earns its price on, and this call happens while the user waits.
                model: (string) config('services.openai.enrich_model', 'gpt-4o-mini'),
                promptVersion: (string) config('services.generation.repair_prompt_version', 'v1'),
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
