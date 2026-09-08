<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Provider;

use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Port\LearningAccountEraser;
use App\Modules\Learning\Application\Port\HomePlanReader;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Learning\Application\Port\LatencyMedianReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\LearnerTimezoneWriter;
use App\Modules\Learning\Application\Port\ModeAdmissionReader;
use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Application\Port\SessionContextReader;
use App\Modules\Learning\Application\Port\SessionOutcomeReader;
use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Application\Port\StatsReader;
use App\Modules\Learning\Application\Port\ProgressSyncReader;
use App\Modules\Learning\Application\Port\SyncCursorReader;
use App\Modules\Learning\Application\Port\TriagedTermsReader;
use App\Modules\Learning\Application\Port\TriageSyncReader;
use App\Modules\Learning\Domain\Repository\ReviewRepository;
use App\Modules\Learning\Domain\Repository\TermExposureRepository;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Repository\TriageRepository;
use App\Modules\Learning\Domain\Service\Fuzz;
use App\Modules\Learning\Domain\Service\Scheduler;
use App\Modules\Learning\Domain\Service\Sm2Scheduler;
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Application\Port\ModeFallbackReporter;
use App\Modules\Learning\Infrastructure\Adapter\LoggingModeFallbackReporter;
use App\Modules\Learning\Application\Port\PlanTermArchiver;
use App\Modules\Learning\Application\Port\PlanTermSweepStore;
use App\Modules\Learning\Application\Port\QaPlanClock;
use App\Modules\Learning\Application\Service\QaClockShift;
use App\Modules\Learning\Application\Service\ShiftableClock;
use App\Modules\Learning\Application\Port\QaReportStore;
use App\Modules\Learning\Infrastructure\Qa\CachedQaPlanClock;
use App\Modules\Learning\Infrastructure\Qa\FileQaReportStore;
use App\Modules\Shared\Domain\Service\Clock;
use Illuminate\Contracts\Container\Container;
use App\Modules\Learning\Domain\Repository\PlanTermStageRepository;
use App\Modules\Learning\Domain\Repository\PlanSceneRunRepository;
use App\Modules\Learning\Domain\Repository\PlanStagePassageRepository;
use App\Modules\Learning\Application\Query\GetPlanHandler;
use App\Modules\Learning\Application\Command\BuildPlanSessionHandler;
use App\Modules\Learning\Application\Service\PlanDayStateCensus;
use App\Modules\Learning\Application\Service\PlanSittingPlanner;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanSkillRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentEnabledModesReader;
use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
use App\Modules\Learning\Application\Port\PlanStandingsReader;
use App\Modules\Learning\Domain\Service\PlanChoiceFloor;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanDayRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanSkillRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanModeSettingsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanStandingsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanTermArchiver;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanTermSweepStore;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanTermStageRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanSceneRunRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanStagePassageRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentEnabledModesWriter;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentDailyStatsProjector;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentDueTermsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentHomePlanReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentIntroducedTermsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentLearningAccountEraser;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentProgressExistenceReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentProgressSnapshotReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentProgressSyncReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentReviewRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentSessionContextReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentSessionOutcomeReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentStatsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentSyncCursorReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentStudySessionRepository;
use App\Modules\Learning\Infrastructure\Adapter\IdentityLearnerProfileReader;
use App\Modules\Learning\Infrastructure\Adapter\IdentityLearnerTimezoneWriter;
use App\Modules\Learning\Infrastructure\Eloquent\CachedLatencyMedianReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTermExposureRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTermProgressRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTriagedTermsReader;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTriageRepository;
use App\Modules\Learning\Infrastructure\Eloquent\EloquentTriageSyncReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermProgressRepository::class, EloquentTermProgressRepository::class);
        $this->app->bind(ReviewRepository::class, EloquentReviewRepository::class);
        $this->app->bind(TermExposureRepository::class, EloquentTermExposureRepository::class);
        $this->app->bind(TriageRepository::class, EloquentTriageRepository::class);
        $this->app->bind(TriagedTermsReader::class, EloquentTriagedTermsReader::class);
        $this->app->bind(TriageSyncReader::class, EloquentTriageSyncReader::class);
        $this->app->bind(SyncCursorReader::class, EloquentSyncCursorReader::class);
        $this->app->bind(ProgressSyncReader::class, EloquentProgressSyncReader::class);
        $this->app->bind(LearnerProfileReader::class, IdentityLearnerProfileReader::class);
        $this->app->bind(LearnerTimezoneWriter::class, IdentityLearnerTimezoneWriter::class);
        $this->app->bind(LatencyMedianReader::class, CachedLatencyMedianReader::class);
        $this->app->bind(SessionContextReader::class, EloquentSessionContextReader::class);
        $this->app->bind(SessionOutcomeReader::class, EloquentSessionOutcomeReader::class);
        $this->app->bind(StudySessionRepository::class, EloquentStudySessionRepository::class);
        $this->app->bind(DueTermsReader::class, EloquentDueTermsReader::class);
        $this->app->bind(ProgressExistenceReader::class, EloquentProgressExistenceReader::class);
        $this->app->bind(ProgressSnapshotReader::class, EloquentProgressSnapshotReader::class);
        $this->app->bind(IntroducedTermsReader::class, EloquentIntroducedTermsReader::class);
        $this->app->bind(HomePlanReader::class, EloquentHomePlanReader::class);
        $this->app->bind(StatsProjector::class, EloquentDailyStatsProjector::class);
        $this->app->bind(StatsReader::class, EloquentStatsReader::class);
        $this->app->bind(LearningAccountEraser::class, EloquentLearningAccountEraser::class);
        $this->app->bind(Scheduler::class, static fn (): Sm2Scheduler => new Sm2Scheduler(Fuzz::random()));

        // The mode set is DATA now (learning_mode_settings), not a compile-time value, because it
        // depends on who is asking — so it is a reader, singleton only for its per-request memo.
        // `config/learning.php` survives as the seed for the global row and as the emergency
        // fallback if that row is ever deleted; nothing reads it on the hot path.
        // Singleton on the CONCRETE class, with the port aliased to it: bound the other way round,
        // anything that asks for the concrete reader (the writer, to invalidate the memo) would get
        // a second instance and the invalidation would land on nobody.
        // The admission matrix lives in the same table and is served by the same reader: one query,
        // one memo, and no way for a session to be dealt under one snapshot of the policy and
        // graded under another.
        $this->app->singleton(EloquentEnabledModesReader::class);
        $this->app->alias(EloquentEnabledModesReader::class, EnabledModesReader::class);
        $this->app->alias(EloquentEnabledModesReader::class, ModeAdmissionReader::class);
        $this->app->bind(EnabledModesWriter::class, EloquentEnabledModesWriter::class);
        $this->app->bind(ModeFallbackReporter::class, LoggingModeFallbackReporter::class);

        // ---- learning plans ----------------------------------------------------------------
        // The two ports a plan needs that only Generation can fulfil — PlanOutlinePort and
        // DispatchesPlanDay — are bound in GenerationServiceProvider, beside the prompt files and
        // the model catalogue they are made of. Everything below is Learning's own.
        $this->app->bind(PlanRepository::class, EloquentPlanRepository::class);
        $this->app->bind(PlanDayRepository::class, EloquentPlanDayRepository::class);
        $this->app->bind(PlanSkillRepository::class, EloquentPlanSkillRepository::class);
        $this->app->bind(PlanTermArchiver::class, EloquentPlanTermArchiver::class);
        $this->app->bind(PlanTermSweepStore::class, EloquentPlanTermSweepStore::class);
        $this->app->bind(PlanTermStageRepository::class, EloquentPlanTermStageRepository::class);
        $this->app->bind(PlanSceneRunRepository::class, EloquentPlanSceneRunRepository::class);
        // ЖУРНАЛ ПРОЙДЕННЫХ ЭТАПОВ (наряд DAY-GATE-1, доработка) — append-only: «пройден» это
        // событие, а не пересчёт долга на сегодняшний день.
        $this->app->bind(PlanStagePassageRepository::class, EloquentPlanStagePassageRepository::class);
        // ДЕВ-ДВЕРЬ СМЕНЫ ДНЕЙ (наряд DAY-FIX-2) — сдвиг «сегодня» QA-аккаунта живёт в кэше, не в
        // таблице; замки — те же, что у входа без пароля, сложенные в `qa_tools` пользователя.
        $this->app->bind(QaPlanClock::class, CachedQaPlanClock::class);
        // «ЖАЛОБА» С ТЕЛЕФОНА (наряд DAY-GATE-1, Ч.0.5) — на диск того же контейнера, наружу
        // ничего. Путь задаётся здесь: `storage_path` — это Laravel, а порту про фреймворк знать
        // нечего.
        $this->app->bind(
            QaReportStore::class,
            static fn (Container $app): QaReportStore => new FileQaReportStore(
                $app->make(Clock::class),
                storage_path('qa-reports'),
            ),
        );
        // …и сам сдвиг — ОДИН держатель на запрос, который читает каждый Clock в контейнере: сервис,
        // собранный до middleware, видит сдвинутый день так же, как собранный после.
        $this->app->singleton(QaClockShift::class);
        $this->app->extend(
            Clock::class,
            static fn (Clock $base, Container $app): Clock => new ShiftableClock($base, $app->make(QaClockShift::class)),
        );
        // ПОРОГ «СРАЗУ» — продуктовое суждение, не константа: экран плана готовности процентом не
        // рисует, но число обязано двигаться из конфига, а не из выката.
        $this->app->when(BuildPlanSessionHandler::class)
            ->needs('$sceneRunKnobs')
            ->give(static fn (): array => [
                'fast_seconds' => (int) config('learning.plan.scene_run.fast_seconds', 3),
                // ПОЛ СТОРОЖА — 15 СЕКУНД (наряд SPEECH-2, Ч.2.1). `listen_seconds` теперь ограничивает
                // ОБЩУЮ длину записи, а не окно ожидания первого слова, и запись, закрытая раньше
                // пятнадцати секунд, режет длинную фразу на полуслове. Пол стоит и здесь, и в движке
                // на телефоне: конфиг, уехавший ниже, не должен уметь этого даже на одном экране.
                'listen_seconds' => max(15, (int) config('learning.plan.scene_run.listen_seconds', 15)),
                'skip_after_seconds' => (int) config('learning.plan.scene_run.skip_after_seconds', 5),
                'turn_seconds' => (int) config('learning.plan.scene_run.turn_seconds', 20),
            ]);
        $this->app->when(GetPlanHandler::class)
            ->needs('$readyFastShare')
            ->give(static fn (): float => (float) config('learning.plan.scene_run.ready_fast_share', 0.7));
        // БЮДЖЕТ ДНЯ (наряд DAY-FIX-2, Ч.2; DAY-FIX-3, Ч.4) — материал ≤ 45, разговор ≤ 25, слова
        // ≤ 12, спасатели ≤ 5, секунды на карточку, варианты перевода слова. Читают планировщик
        // посадки и перепись состояния дня, из одного места.
        $budget = static fn (): array => [
            'material_max_cards' => (int) config('learning.plan.budget.material_max_cards', 45),
            'conversation_max_cards' => (int) config('learning.plan.budget.conversation_max_cards', 25),
            'words_section_cards' => (int) config('learning.plan.budget.words_section_cards', 40),
            'rescue_warmup_cards' => (int) config('learning.plan.budget.rescue_warmup_cards', 5),
            'card_seconds' => (int) config('learning.plan.budget.card_seconds', 16),
            'word_choice_options' => (int) config('learning.plan.budget.word_choice_options', 4),
        ];
        $this->app->when(PlanSittingPlanner::class)->needs('$budget')->give($budget);
        $this->app->when(PlanDayStateCensus::class)->needs('$budget')->give($budget);

        // ОКНО, ПОСЛЕ КОТОРОГО ЗАВИСШИЙ ДЕНЬ ПЕРЕЗАХВАТЫВАЕТСЯ (вердикт по GEN-1) —
        // `config/learning.php → plan.generation_stale_minutes`.
        $this->app->when(\App\Modules\Learning\Application\Service\PlanDayStaleSweeper::class)
            ->needs('$staleMinutes')
            ->give(static fn (): int => max(1, (int) config('learning.plan.generation_stale_minutes', 10)));

        // ЗАСЧИТЫВАТЬ ЛИ `speaking_keys` НА ГОВОРЕНИИ (наряд GEN-1) — тумблер, выключенный, пока
        // телефон судит по одному ключу; см. `config/learning.php → plan.speaking_keys_graded`.
        $this->app->when(\App\Modules\Learning\Application\Command\SubmitReviewsHandler::class)
            ->needs('$speakingKeysGraded')
            ->give(static fn (): bool => (bool) config('learning.plan.speaking_keys_graded', false));

        // ПОРОГИ ЗАЧЁТА РЕЧИ (наряд SPEECH-2, Ч.3.3) — из конфига, не из литералов в грейдере.
        // Одни и те же числа едут телефону в контракте сессии, поэтому источник обязан быть один:
        // грейдер, судящий по 0.9, и экран, судящий по 0.7, — это «Не то» над зачтённым ответом.
        $this->app->singleton(
            \App\Modules\Learning\Domain\ValueObject\SpeechGradingRules::class,
            static fn (): \App\Modules\Learning\Domain\ValueObject\SpeechGradingRules => \App\Modules\Learning\Domain\ValueObject\SpeechGradingRules::fromConfig(
                (array) config('learning.plan.speech', []),
            ),
        );
        // «Из плана: Отпуск в Италии» — what a review card of the top-up says about itself.
        // Singleton for the same reason the global reader is one: a per-request memo over one query.
        // A DIFFERENT instance from that reader even though it is the same table — the two read
        // disjoint scopes, and sharing a memo would mean one of them filtering the other's rows out
        // of a cache it did not build.
        $this->app->singleton(EloquentPlanModeSettingsReader::class);
        $this->app->alias(EloquentPlanModeSettingsReader::class, PlanModeSettingsReader::class);
        $this->app->bind(PlanStandingsReader::class, EloquentPlanStandingsReader::class);
        // HOW FAR A PLAN'S CHOICE CARD MAY SHRINK. One instance, injected into both the checklist
        // and the assembler, because the two must never disagree about which cards exist — see
        // {@see PlanChoiceFloor}. Configuration, like the length band, for the same reason.
        $this->app->singleton(PlanChoiceFloor::class, static fn (): PlanChoiceFloor => new PlanChoiceFloor(
            (int) config('learning.plan.mc_min_options', PlanChoiceFloor::DEFAULT_MIN_OPTIONS),
        ));
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
