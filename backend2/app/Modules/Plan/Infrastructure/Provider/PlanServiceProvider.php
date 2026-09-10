<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Provider;

use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\NativeDistractorSource;
use App\Modules\Plan\Application\Port\PlanAccountEraser;
use App\Modules\Plan\Application\Port\PlanCollectionWriter;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Application\Port\PlanListReader;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Check\BlueprintChecker;
use App\Modules\Plan\Domain\Check\LessonChecker;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\CheckModes;
use App\Modules\Plan\Infrastructure\Adapter\GenerationLineSpeaker;
use App\Modules\Plan\Infrastructure\Adapter\IdentityLearnerCalendar;
use App\Modules\Plan\Infrastructure\Adapter\PexelsPlanImageFinder;
use App\Modules\Plan\Infrastructure\Adapter\QueuedPlanDispatcher;
use App\Modules\Plan\Infrastructure\Adapter\StampedBuildVersion;
use App\Modules\Plan\Infrastructure\Adapter\VocabularyNativeDistractorSource;
use App\Modules\Plan\Infrastructure\Adapter\VocabularyPlanCollectionWriter;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentCheckCounters;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentDayCardRepository;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentLineAudioStore;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentPlanAccountEraser;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentPlanRepository;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentPlanTermRepository;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Factory as Disks;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PlanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The aggregate's repository doubles as the list reader and the scene locator: one mapper,
        // one set of queries, no second way to load a plan.
        $this->app->singleton(EloquentPlanRepository::class);
        $this->app->alias(EloquentPlanRepository::class, PlanRepository::class);
        $this->app->alias(EloquentPlanRepository::class, PlanListReader::class);
        $this->app->alias(EloquentPlanRepository::class, SceneLocator::class);
        $this->app->bind(DayCardRepository::class, EloquentDayCardRepository::class);
        $this->app->bind(PlanTermRepository::class, EloquentPlanTermRepository::class);
        $this->app->bind(CheckCounters::class, EloquentCheckCounters::class);
        $this->app->bind(NativeDistractorSource::class, VocabularyNativeDistractorSource::class);
        $this->app->bind(PlanAccountEraser::class, EloquentPlanAccountEraser::class);
        $this->app->bind(PlanDispatcher::class, QueuedPlanDispatcher::class);
        $this->app->bind(PlanCollectionWriter::class, VocabularyPlanCollectionWriter::class);
        $this->app->bind(PlanImageFinder::class, fn (Container $app): PlanImageFinder => new PexelsPlanImageFinder($app->make(ImageSearchPort::class)));

        // Memoised per request: the same learner's zone is asked by the command and by the view.
        $this->app->singleton(IdentityLearnerCalendar::class);
        $this->app->alias(IdentityLearnerCalendar::class, LearnerCalendar::class);

        $this->app->singleton(BuildVersion::class, fn (): BuildVersion => new StampedBuildVersion(
            storage_path('app/commit'),
            (string) config('app.commit', ''),
        ));

        $this->app->singleton(PlanConfig::class, function (): PlanConfig {
            /** @var array<string, array{phrases: int, vocabulary: int, dialogue: int}> $counts */
            $counts = (array) config('plan.counts', []);
            /** @var list<array{text_target: string, text_native: string, pronunciation_native: string}> $kit */
            $kit = self::rescueKit();

            return new PlanConfig(
                counts: $counts,
                buildStaleSeconds: (int) config('plan.build_stale_seconds', 240),
                rescueKit: $kit,
            );
        });

        // THE CHECKS' MODES come from config and nowhere else: a mode flipped in code is a mode
        // nobody can flip back without a deploy.
        $this->app->singleton(LessonChecker::class, fn (): LessonChecker => new LessonChecker(
            CheckModes::fromArray(array_map('strval', (array) config('plan.checks.lesson', []))),
        ));
        $this->app->singleton(BlueprintChecker::class, fn (): BlueprintChecker => new BlueprintChecker(
            CheckModes::fromArray(array_map('strval', (array) config('plan.checks.plan', []))),
        ));

        $this->app->singleton(PlanPromptFiles::class, fn (): PlanPromptFiles => new PlanPromptFiles(
            dirname(__DIR__).'/Prompt',
        ));

        // The model door. `fake` is the whole test suite and offline dev; anything else goes
        // through the catalogue, whose LiveModelGuard is what stops a test buying a plan.
        $this->app->singleton(PlanModelPort::class, function (Container $app): PlanModelPort {
            if ((string) config('plan.model.driver', 'openai') === 'fake') {
                return new FakePlanModel;
            }

            return new ContentModelPlanBuilder(
                catalog: $app->make(ContentModelCatalog::class),
                prompts: $app->make(PlanPromptFiles::class),
                provider: ProviderId::tryFrom((string) config('plan.model.provider', 'openai')) ?? ProviderId::OpenAi,
                planModel: (string) config('plan.model.plan_model', 'gpt-5.4'),
                lessonModel: (string) config('plan.model.lesson_model', 'gpt-5.4'),
                planTimeout: (int) config('plan.model.plan_timeout', 90),
                lessonTimeout: (int) config('plan.model.lesson_timeout', 90),
            );
        });

        $this->app->bind(LineSpeaker::class, fn (Container $app): LineSpeaker => new GenerationLineSpeaker(
            $app->make(SpeechSynthesizerPort::class),
            $app->make(VoiceCatalog::class),
            (bool) config('generation.speech.enabled', false),
        ));
        $this->app->bind(LineAudioStore::class, fn (Container $app): LineAudioStore => new EloquentLineAudioStore(
            $app->make(Disks::class),
            (string) config('plan.audio_disk', 'local'),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migration');

        $routes = __DIR__.'/../../Presentation/Http/routes.php';
        if (is_file($routes)) {
            Route::middleware('api')->prefix('api/v1')->group($routes);
        }
    }

    /**
     * The rescue kit of the deployment's one pair (en ← ru); a pair without a kit gets an empty
     * list, not a crash.
     *
     * @return list<array{text_target: string, text_native: string, pronunciation_native: string}>
     */
    private static function rescueKit(): array
    {
        $kits = (array) config('plan.rescue_kit', []);
        $en = is_array($kits['en'] ?? null) ? $kits['en'] : [];
        $rows = is_array($en['ru'] ?? null) ? $en['ru'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = [
                    'text_target' => (string) ($row['text_target'] ?? ''),
                    'text_native' => (string) ($row['text_native'] ?? ''),
                    'pronunciation_native' => (string) ($row['pronunciation_native'] ?? ''),
                ];
            }
        }

        return $out;
    }
}
