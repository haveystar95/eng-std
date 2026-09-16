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
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\VoiceCap;
use App\Modules\Plan\Application\Service\VoiceFuse;
use App\Modules\Plan\Application\Port\NativeDistractorSource;
use App\Modules\Plan\Application\Port\PlanAccountEraser;
use App\Modules\Plan\Application\Port\PlanCollectionWriter;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Application\Port\PlanListReader;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Port\SceneImageStore;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Application\Port\SlotJudgeQuota;
use App\Modules\Plan\Domain\Check\BlueprintChecker;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CheckModes;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Plan\Infrastructure\Adapter\ArraySlotJudgeQuota;
use App\Modules\Plan\Infrastructure\Adapter\CdnSceneImageStore;
use App\Modules\Plan\Infrastructure\Adapter\GenerationLineSpeaker;
use App\Modules\Plan\Infrastructure\Adapter\IdentityLearnerCalendar;
use App\Modules\Plan\Infrastructure\Adapter\IdentityLearnerGender;
use App\Modules\Plan\Infrastructure\Adapter\PexelsPlanImageFinder;
use App\Modules\Plan\Infrastructure\Adapter\QueuedPlanDispatcher;
use App\Modules\Plan\Infrastructure\Adapter\RedisSlotJudgeQuota;
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
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Plan\Application\Port\LearnerDevices;
use App\Modules\Plan\Application\Port\LearnerHabits;
use App\Modules\Plan\Application\Port\NotifiablePlans;
use App\Modules\Plan\Application\Port\NotificationDispatcher;
use App\Modules\Plan\Application\Port\NotificationLog;
use App\Modules\Plan\Application\Port\PushSender;
use App\Modules\Plan\Domain\Repository\PlanEventRepository;
use App\Modules\Plan\Infrastructure\Adapter\IdentityLearnerDevices;
use App\Modules\Plan\Infrastructure\Adapter\IdentityLearnerHabits;
use App\Modules\Plan\Infrastructure\Adapter\QueuedNotificationDispatcher;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentNotifiablePlans;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentNotificationLog;
use App\Modules\Plan\Infrastructure\Eloquent\EloquentPlanEventRepository;
use App\Modules\Plan\Infrastructure\Push\ApnsProviderToken;
use App\Modules\Plan\Infrastructure\Push\ApnsPushSender;
use App\Modules\Plan\Infrastructure\Push\DryRunPushSender;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
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

        // The journal, the letters and the door to the phone (PLAN-UI-3).
        $this->app->bind(PlanEventRepository::class, EloquentPlanEventRepository::class);
        $this->app->bind(NotificationLog::class, EloquentNotificationLog::class);
        $this->app->bind(NotifiablePlans::class, EloquentNotifiablePlans::class);
        $this->app->bind(NotificationDispatcher::class, QueuedNotificationDispatcher::class);
        $this->app->bind(LearnerDevices::class, IdentityLearnerDevices::class);
        $this->app->bind(LearnerHabits::class, IdentityLearnerHabits::class);
        // No APNs key → dry mode. The same queue and the same log either way; only this door changes.
        $this->app->bind(PushSender::class, function (Container $app): PushSender {
            $key = trim((string) config('services.apns.key_p8', ''));
            if ($key === '') {
                return new DryRunPushSender;
            }

            return new ApnsPushSender(
                new ApnsProviderToken(
                    $app->make(CacheRepository::class),
                    $key,
                    (string) config('services.apns.key_id', ''),
                    (string) config('services.apns.team_id', ''),
                ),
                $app->make(LearnerDevices::class),
                (string) config('services.apns.topic', 'com.denis.engstd'),
                (string) config('services.apns.env', 'sandbox'),
            );
        });
        $this->app->bind(PlanImageFinder::class, fn (Container $app): PlanImageFinder => new PexelsPlanImageFinder($app->make(ImageSearchPort::class)));

        // Memoised per request: the same learner's zone is asked by the command and by the view.
        $this->app->singleton(IdentityLearnerCalendar::class);
        $this->app->alias(IdentityLearnerCalendar::class, LearnerCalendar::class);
        $this->app->bind(LearnerGender::class, IdentityLearnerGender::class);

        $this->app->singleton(BuildVersion::class, fn (): BuildVersion => new StampedBuildVersion(
            storage_path('app/commit'),
            (string) config('app.commit', ''),
        ));

        $this->app->singleton(PlanConfig::class, function (): PlanConfig {
            /** @var array<string, array{vocabulary: int, dialogue: int}> $counts */
            $counts = (array) config('plan.counts', []);
            /** @var list<array{text_target: string, text_native: string, pronunciation_native: string}> $kit */
            $kit = self::rescueKit();
            $languages = array_values(array_unique(array_filter(
                array_map(static fn (mixed $code): string => strtolower(trim(is_string($code) ? $code : '')), (array) config('plan.languages', ['en', 'de'])),
                static fn (string $code): bool => preg_match('/^[a-z]{2,5}$/', $code) === 1,
            )));

            $pace = [];
            foreach ((array) config('plan.pace', DayPace::DEFAULTS) as $kind => $seconds) {
                if (is_string($kind) && is_numeric($seconds)) {
                    $pace[$kind] = (int) $seconds;
                }
            }

            return new PlanConfig(
                counts: $counts,
                buildStaleSeconds: (int) config('plan.build_stale_seconds', 240),
                rescueKit: $kit,
                languages: $languages,
                pace: $pace,
                slotJudgeDailyCap: (int) config('plan.slot_judge.daily_cap', 60),
            );
        });

        // THE PACE OF A DAY (SESSION-1a): seconds per card by kind, from `plan.pace` — tuned in config, never in code.
        $this->app->singleton(DayPace::class, fn (Container $app): DayPace => new DayPace($app->make(PlanConfig::class)->pace));

        // THE SLOT JUDGE'S DAILY QUOTA (SESSION-1a): Redis in the stack, the process's memory under test
        // (phpunit.xml) — one instance per application, so a test's calls add up and the next test starts at zero.
        $this->app->singleton(ArraySlotJudgeQuota::class);
        $this->app->bind(SlotJudgeQuota::class, fn (Container $app): SlotJudgeQuota => (string) config('plan.slot_judge.quota_store', 'redis') === 'array'
            ? $app->make(ArraySlotJudgeQuota::class)
            : $app->make(RedisSlotJudgeQuota::class));

        // THE PLAN CHECKS' MODES come from config and nowhere else: a mode flipped in code is a mode
        // nobody can flip back without a deploy. The lesson validator has no modes — it only counts.
        $this->app->singleton(BlueprintChecker::class, fn (): BlueprintChecker => new BlueprintChecker(
            CheckModes::fromArray(array_map('strval', (array) config('plan.checks.plan', []))),
        ));

        // WHAT THE LESSON VALIDATOR KNOWS OF EACH LANGUAGE — `config/lesson/lang/<code>.php` (наряд GEN-2b). A code
        // with no file is a language with no pack: its checks are skipped and counted, never guessed.
        $this->app->singleton(LanguagePacks::class, fn (): LanguagePacks => new LanguagePacks((array) config('lesson.lang', [])));

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
                repairModel: (string) config('plan.model.repair_model', 'gpt-5.4'),
                judgeModel: (string) config('plan.model.judge_model', 'gpt-5.4-mini'),
                slotJudgeTimeout: (int) config('plan.slot_judge.timeout', ContentModelPlanBuilder::SLOT_JUDGE_TIMEOUT),
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
        // The voice fuse (TTS-2): the plan size it counts against when the vendor would not say, and the share of it
        // below which nothing is bought — read here so Application stays clear of config().
        $this->app->bind(VoiceFuse::class, fn (Container $app): VoiceFuse => new VoiceFuse(
            $app->make(LineSpeaker::class),
            $app->make(LineAudioStore::class),
            $app->make(Clock::class),
            (int) config('generation.speech.monthly_credits', 10000),
            (float) config('generation.speech.fuse_share', 0.10),
        ));
        // The credits cap of one run of purchases (TTS-2): a voice job, a backfill.
        $this->app->bind(VoiceCap::class, fn (Container $app): VoiceCap => new VoiceCap(
            $app->make(LineSpeaker::class),
            (int) config('generation.speech.job_credits_cap', 3000),
        ));

        // The sized copies of scene photos. The fake image driver (the whole test suite, offline
        // dev) fetches nothing — the same switch that keeps the photo search off the wire.
        $this->app->bind(SceneImageStore::class, fn (Container $app): SceneImageStore => new CdnSceneImageStore(
            $app->make(Disks::class),
            (string) config('plan.image_disk', 'local'),
            $app->make(OutboundCallContext::class),
            config('services.generation.image_driver') !== 'fake',
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
