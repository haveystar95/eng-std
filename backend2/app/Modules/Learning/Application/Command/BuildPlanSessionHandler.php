<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Dto\PlanDialogueTurnView;
use App\Modules\Learning\Application\Dto\PlanDialogueView;
use App\Modules\Learning\Application\Dto\PlanLineAudioView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Learning\Application\Dto\PlanSessionView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\ModeAdmissionReader;
use App\Modules\Learning\Application\Port\OrdersLineSpeech;
use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
use App\Modules\Learning\Application\Service\CardLanguageResolver;
use App\Modules\Learning\Application\Service\LineAudioIndex;
use App\Modules\Learning\Application\Service\PlanDayPassing;
use App\Modules\Learning\Application\Service\PlanDayStateCensus;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Application\Service\PlanSittingLayout;
use App\Modules\Learning\Application\Service\PlanSittingPlanner;
use App\Modules\Learning\Application\Service\StudyCardAssembler;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Exception\PlanDayLocked;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Exception\PlanSittingEmpty;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\PlanTermStageRepository;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\Service\PlanAnswerOptions;
use App\Modules\Learning\Domain\Service\PlanDayPassage;
use App\Modules\Learning\Domain\Service\PlanDialogueChain;
use App\Modules\Learning\Domain\Service\PlanHearOptions;
use App\Modules\Learning\Domain\Service\PlanKnobSupport;
use App\Modules\Learning\Domain\Service\PlanSessionSections;
use App\Modules\Learning\Domain\Service\PlanSittings;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\Service\SituationalPrompt;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStage;
use App\Modules\Learning\Domain\ValueObject\PlanDayStageState;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanDialogueMove;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;
use App\Modules\Learning\Domain\ValueObject\PlanTurnLevel;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;
use App\Modules\Learning\Domain\ValueObject\SituationalTurn;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

/**
 * THE SESSION OF ONE DAY OF A PLAN.
 *
 * The ordinary session assembler ({@see BuildStudySessionHandler}) picks the words and then asks the
 * ladder which trainer each one is owed. A plan session inverts that: the DAY decides the words and
 * the STAGE decides the trainers, so what this handler does is take a running order and force a
 * mode onto every slot. Everything below the mode — the card, the options, the grading, the offline
 * check — is the app's ordinary machinery, untouched, which is the whole reason a plan day owns an
 * ordinary collection.
 *
 * ## The running order is PLANNED elsewhere (наряд DAY-FIX-2, Ч.3)
 *
 * Which cards a day deals, in which order, with which conversations and under which budget is
 * {@see PlanSittingPlanner}'s answer, and this handler only turns that answer into cards, persists
 * the session and looks up the audio. The split exists because the plan screen has to say «идёт ·
 * около 6 минут» about the SAME sitting this handler deals, and three screens used to count three
 * different days when each counted on its own.
 *
 * ## A SITTING IS MADE OF THIS PLAN'S CARDS AND OF NOTHING ELSE
 *
 * There is no third bucket, and «no» here means NOT CALLED. There used to be: whatever else the
 * planner had due, poured in behind the day. Two live runs said what that is actually worth
 * (DECISIONS п. 204, 212), and the rule is cancelled rather than tightened: the read itself is
 * scoped ({@see planViews()}), so the session cannot deal a word this plan did not enrol.
 *
 * The other direction is the same rule seen from the pool: a plan's words are dealt BY THE PLAN and
 * by nothing else — out of the ordinary session and out of «Повторить N»
 * ({@see \App\Modules\Learning\Infrastructure\Eloquent\PlanHeldTerms}) while it runs, and out of
 * them for good when it ends.
 *
 * ## «Когда» is not this handler's business
 *
 * Nothing here reads or writes `due_at`, an interval or an ease. The plan says WHICH card; the
 * repetition planner says WHEN a word comes back, and it learns that from the review log.
 *
 * ## A day opened ahead of the focus
 *
 * STRICT, like every other day of the plan (E2E-SIM-2, С-1): it is dealt exactly what it would be
 * dealt in its turn, and since none of its cards has been introduced, that is stage A. What it does
 * not get is the seam — the learner came to look ahead, not to revise.
 */
final readonly class BuildPlanSessionHandler
{
    /** Bound on the due read: the same order of magnitude as the ordinary session's. */
    private const DUE_CAP = 100;

    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
        private PlanModeSettingsReader $planSettings,
        /**
         * THE PROGRESS ROWS OF THIS PLAN'S OWN CARDS — read by term id, never by `due_at`
         * ({@see planViews()}).
         */
        private DueTermsReader $dueInPlan,
        private UserCollectionTermsReader $collectionTerms,
        private TermContentReader $content,
        private CardLanguageResolver $languages,
        private StudyCardAssembler $assembler,
        private EnabledModesReader $enabledModes,
        private ModeAdmissionReader $admission,
        private StudySessionRepository $sessions,
        private TransactionManager $tx,
        private Clock $clock,
        /**
         * «День пройден», written by the same code that writes it when a sitting ENDS
         * ({@see PlanDayPassing}).
         */
        private PlanDayPassing $passing,
        /**
         * КАКИЕ РЕПЛИКИ УЖЕ ЗВУЧАТ ФАЙЛОМ (наряд TTS-1). Не nullable и без дефолта: пустой ответ —
         * законное состояние, а «сборка не спросила» — нет.
         */
        private LineAudioIndex $lineAudio,
        private OrdersLineSpeech $speechOrders,
        /** НА КАКОМ УРОВНЕ СТРОГОСТИ СТОИТ КАЖДЫЙ ХОД ЭТОГО ПЛАНА (наряд SCENE-RUN, Ч.1). */
        private PlanTermStageRepository $termStages,
        /** ЧТО РАЗДАЁТ ДЕНЬ — the bucket rules, the order, the chains and the budget (Ч.3). */
        private PlanSittingPlanner $planner,
        /**
         * СЛОВО И МИНУТЫ ДНЯ — the same census the plan payload reads, so the sitting header and
         * the plan tab cannot say two things about one day (Ч.3.3).
         */
        private PlanDayStateCensus $census,
        /**
         * Секунды прогона сцены — `config/learning.php → plan.scene_run`.
         *
         * @var array{fast_seconds: int, listen_seconds: int, skip_after_seconds: int, turn_seconds: int}
         */
        private array $sceneRunKnobs,
        /** Where a situational card's «Ситуация» comes from — pure, and stated in Domain. */
        private SituationalPrompt $situations = new SituationalPrompt(),
    ) {}

    public function __invoke(BuildPlanSession $command): PlanSessionView
    {
        $plan = $this->plans->findById(PlanId::fromString($command->planId));
        if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
            throw PlanNotFound::withId($command->planId);
        }

        $days = $this->days->listForPlan($plan->id());
        $progress = $this->progress->forPlan($plan, $days);
        $dayIndex = $command->dayIndex ?? $progress->focusDayIndex;
        $day = $this->dayAt($days, $dayIndex) ?? throw PlanNotFound::withId($command->planId);

        // The focus has moved past every day the learner has finished. Written here — in the command
        // path, never on a read — so the generation policy (which queues day n+1 when day n is DONE)
        // and the screen agree about what is finished.
        $this->passing->mark($plan, $days, $progress);

        // EVERY TEACHING DAY IS STRICT — its turn or not. The final day is a run-through (Д-27): it
        // introduces nothing, owns no collection, and its sitting is the прогон of every scene.
        $strict = $day->kind() === PlanDayKind::Intro;
        $knobs = $this->planSettings->knobsFor($plan->level());
        $termStages = $this->termStages->forPlan($plan->id());

        // ЗАМОК ДНЯ (наряд DAY-GATE-1, Ч.1.2): день N+1 открывается, только когда день N пройден.
        // Стоит ЗДЕСЬ, а не только на экране: замок, который знает один клиент, — это не замок.
        if ($strict) {
            $blocker = self::lockedBy($days, $progress, $dayIndex);
            if ($blocker !== null) {
                throw PlanDayLocked::behind($command->planId, $dayIndex, $blocker);
            }
        }

        $layout = $strict
            ? $this->planner->plan($plan, $progress, $dayIndex, $knobs, $termStages)
            : $this->planner->rehearsal($plan, $progress);

        // СЛОВО И МИНУТЫ ДНЯ — по ЦЕЛОМУ дню, до раскроя по этапам: экран дня должен знать про день
        // всё, а раздаётся ему только текущий этап (Ч.3.3 наряда DAY-FIX-3 + Ч.1.4 этого).
        $state = $this->census->of($plan, $days, $progress, $dayIndex, $knobs, $termStages, $layout);

        // …И ТЕПЕРЬ — ТОЛЬКО ОДИН ЭТАП. «Продолжить» ведёт в текущий; названный клиентом этап —
        // это всегда «Повторить ошибки», единственная дверь не по порядку.
        $dealtStage = null;
        if ($strict) {
            $dealtStage = $command->stage ?? PlanDayPassage::current($state->dayStages);
            if ($command->stage === PlanDayStage::Retrain) {
                $layout = $this->planner->retrain($plan, $progress, $dayIndex, $knobs, $termStages);
            } elseif ($dealtStage !== null) {
                self::assertOpen($command, $state->dayStages, $dealtStage, $dayIndex);
                $layout = $layout->onlyStage($dealtStage);
            }
        }

        $tasks = $strict
            ? $this->strictTasks($plan, $progress, $dayIndex, $knobs, $layout)
            : $this->rehearsalTasks($plan, $progress, $knobs, $layout);
        $dialogues = $layout->chains;

        // ВЫРОЖДЕННАЯ ПОСАДКА НЕ СОБИРАЕТСЯ НИКОГДА (наряд DAY-GATE-1, Ч.1.4). Одна интро-карточка и
        // ноль упражнений — это то, что живой прогон 07.09 получил по «Дотренировать», а следом
        // шесть сессий подряд вообще без карточек, ни одна из которых не была закрыта.
        //
        // На НЕ пройденном этапе такого быть не может по построению: этап текущий ровно тогда, когда
        // он что-то должен. Остаётся один случай — спек есть, а карточку сборщик построить не смог
        // (пятый фильтр, {@see PlanStandings}), — и он честно отвечает отказом, а не пустым экраном.
        if ($tasks === []) {
            throw PlanSittingEmpty::forDay($command->planId, $dayIndex, $dealtStage?->value);
        }

        $sessionId = $command->sessionId ?? StudySessionId::generate();
        $this->persist($sessionId, $plan, $day, $tasks, $strict);

        // ОДИН ДОПОЛНИТЕЛЬНЫЙ ПОКАЗ В ДЕНЬ — отмечается в момент выдачи, а не ответа: дверь открыта
        // один раз, и человек, закрывший экран не ответив, потратил свой повтор. Иначе «повторить»
        // стало бы способом молотить одну реплику весь вечер.
        if ($command->stage === PlanDayStage::Retrain) {
            $this->markRetrained($plan, $termStages, $tasks, $progress->today);
        }

        $audio = $this->lineAudioFor($tasks, $dialogues, $plan->targetLang()->value);
        // …И ЗАКАЗАТЬ ТО, ЧЕГО НЕ ХВАТИЛО. «Готовим озвучку» — состояние на секунды, а не навсегда
        // (канон §7): реплика, у которой файла нет и заказа не было, держала бы экран-диалог в
        // ожидании вечно. Посадка видит недостачу первой и единственная.
        $this->orderMissingSpeech($plan, $dialogues, $audio);

        return new PlanSessionView(
            sessionId: $sessionId->value,
            planId: $plan->id()->value,
            dayIndex: $dayIndex,
            strict: $strict,
            focusDayIndex: $progress->focusDayIndex,
            tasks: $tasks,
            knobs: $knobs->toArray(),
            // The seam. Counted rather than assumed: `assembleTasks()` drops a task whose card the
            // assembler refused, so the number of DAY tasks that survived is not the number of day
            // specs that went in.
            dayTaskCount: count(array_filter(
                $tasks,
                static fn (PlanSessionTaskView $t): bool => $t->section === PlanSessionTaskView::SECTION_DAY,
            )),
            // WHERE IT IS HONEST TO STOP: «Материал», then «Разговор» (DAY-FIX-3, Ч.4). Computed
            // from the tasks that actually survived assembly, for the same reason `dayTaskCount` is.
            sittings: PlanSittings::split(array_map(self::sectionKeyOf(...), $tasks)),
            sittingPlan: PlanSittings::plan(array_map(self::sectionKeyOf(...), $tasks)),
            materialMinutes: $state->materialMinutes,
            conversationMinutes: $state->conversationMinutes,
            // THE CONVERSATIONS, in the order the sitting reaches them — the dialogue screen's whole
            // input beside the tasks themselves.
            dialogues: self::dialoguesWithAudio($dialogues, $audio),
            // ОЗВУЧКА (наряд TTS-1): всё, что эта посадка может сыграть файлом, — одним списком,
            // чтобы телефон скачал её ЦЕЛИКОМ на входе в день.
            lineAudio: $audio,
            // СЕКУНДЫ ПРОГОНА — из конфига, а не из кода экрана.
            sceneRun: $this->sceneRunKnobs,
            dayState: $state->state,
            minutesLeft: $state->minutesLeft,
            // КАКОЙ ЭТАП ЭТА ПОСАДКА И ЧТО У ДНЯ ОСТАЛОСЬ — шапка присеста и экран дня читают одно
            // и то же (наряд DAY-GATE-1, Ч.1.4).
            stage: $dealtStage,
            dayStages: $state->dayStages,
        );
    }

    /**
     * ЧТО ДЕРЖИТ ЭТОТ ДЕНЬ ЗАКРЫТЫМ — индекс предыдущего непройденного дня, или null (Ч.1.2).
     *
     * Правило одно: пока день N не пройден, дня N+1 нет. Не «по расписанию» и не «по дате» — по
     * факту прохождения, тому же, что двигает фокус ({@see PlanProgress}). Забежать вперёд нельзя
     * даже с прямой ссылкой: живой прогон 07.09 показал вкладку, где день 2 открывался поверх
     * незакрытого дня 1, и человек не понимал, что от него хотят.
     *
     * @param  list<PlanDay>  $days
     */
    public static function lockedBy(array $days, PlanProgressView $progress, int $dayIndex): ?int
    {
        foreach ($days as $day) {
            if ($day->kind() !== PlanDayKind::Intro || $day->dayIndex() >= $dayIndex) {
                continue;
            }
            if ($day->status() === PlanDayStatus::Done) {
                continue;
            }
            if (! ($progress->days[$day->dayIndex()]->passed ?? false)) {
                return $day->dayIndex();
            }
        }

        return null;
    }

    /**
     * Названный этап должен быть открыт. Запертый — отказ, а не тихая подмена на текущий: клиент,
     * который просит «Скажи сам» поверх неоконченного разговора, ошибается, и молчаливая подмена
     * спрятала бы ошибку под правильным экраном.
     *
     * @param  list<array{stage: PlanDayStage, state: PlanDayStageState, cards: int}>  $stages
     */
    private static function assertOpen(BuildPlanSession $command, array $stages, PlanDayStage $stage, int $dayIndex): void
    {
        foreach ($stages as $row) {
            if ($row['stage'] === $stage && $row['state'] === PlanDayStageState::Locked) {
                throw PlanDayLocked::stage($command->planId, $dayIndex, $stage->value);
            }
        }
    }

    /**
     * Отметить, что эти реплики сегодня уже брали «Повторить ошибки».
     *
     * @param  array<string, PlanTermStage>  $termStages
     * @param  list<PlanSessionTaskView>  $tasks
     */
    private function markRetrained(LearningPlan $plan, array $termStages, array $tasks, string $today): void
    {
        $seen = [];
        foreach ($tasks as $task) {
            $termId = $task->card->termId;
            if (isset($seen[$termId])) {
                continue;
            }
            $seen[$termId] = true;
            $stage = $termStages[$termId] ?? new PlanTermStage($plan->id()->value, $termId);
            $this->termStages->save($stage->afterRetrain($today));
        }
    }

    /**
     * Ходы диалога, каждый со своим адресом озвучки. Отдельным проходом, потому что цепочки
     * собираются раньше, чем известен индекс озвучки, а {@see PlanDialogueTurnView} — readonly.
     *
     * @param  list<PlanDialogueView>  $dialogues
     * @param  list<PlanLineAudioView>  $audio
     * @return list<PlanDialogueView>
     */
    private static function dialoguesWithAudio(array $dialogues, array $audio): array
    {
        if ($audio === []) {
            return $dialogues;
        }

        $byTerm = [];
        foreach ($audio as $row) {
            $byTerm[$row->termId] = $row->audioId;
        }

        return array_map(static fn (PlanDialogueView $d): PlanDialogueView => new PlanDialogueView(
            dayIndex: $d->dayIndex,
            sceneTitle: $d->sceneTitle,
            sceneIntro: $d->sceneIntro,
            turns: array_map(static fn (PlanDialogueTurnView $t): PlanDialogueTurnView => new PlanDialogueTurnView(
                turn: $t->turn,
                termId: $t->termId,
                text: $t->text,
                translation: $t->translation,
                shelf: $t->shelf,
                audioId: $byTerm[$t->termId] ?? null,
                level: $t->level,
                pairKind: $t->pairKind,
            ), $d->turns),
            runReady: $d->runReady,
        ), $dialogues);
    }

    /**
     * ЧТО ИЗ ЭТОЙ ПОСАДКИ УЖЕ ЗВУЧИТ ФАЙЛОМ — карточки задач и ходы диалогов, спрошенные одним
     * запросом. Оба источника: ход цепочки может не иметь сегодня своей задачи, а спасатель
     * разогрева не стоит ни в одной цепочке.
     *
     * @param  list<PlanSessionTaskView>  $tasks
     * @param  list<PlanDialogueView>  $dialogues
     * @return list<PlanLineAudioView>
     */
    private function lineAudioFor(array $tasks, array $dialogues, string $targetLang): array
    {
        $texts = [];
        foreach ($tasks as $task) {
            $texts[$task->card->termId] = $task->card->answer;
        }
        foreach ($dialogues as $dialogue) {
            foreach ($dialogue->turns as $turn) {
                $texts[$turn->termId] = $turn->text;
            }
        }
        if ($texts === []) {
            return [];
        }

        $out = [];
        foreach ($this->lineAudio->forTerms(array_keys($texts), $targetLang) as $termId => $audioId) {
            $out[] = new PlanLineAudioView($termId, (string) $texts[$termId], $audioId);
        }

        return $out;
    }

    /**
     * РЕПЛИКИ ПОСАДКИ БЕЗ ФАЙЛА — заказать их озвучку и идти дальше.
     *
     * Считается по ЦЕПОЧКАМ: экран-диалог играет разговор целиком, и молчащий ход — это ход, у
     * которого сегодня своей карточки может и не быть. Полки — те же две, что озвучивает станок:
     * «Тебе скажут» и спасатели. Заказ идемпотентен, тумблер трубы стоит в диспетчере.
     *
     * @param  list<PlanDialogueView>  $dialogues
     * @param  list<PlanLineAudioView>  $audio
     */
    private function orderMissingSpeech(LearningPlan $plan, array $dialogues, array $audio): void
    {
        $voiced = [];
        foreach ($audio as $row) {
            $voiced[$row->termId] = true;
        }

        $missingDays = [];
        foreach ($dialogues as $dialogue) {
            foreach ($dialogue->turns as $turn) {
                if (! in_array($turn->shelf, [PlanDialogueChain::SHELF_HEAR, PlanSittingPlanner::SHELF_RESCUE], true)) {
                    continue;
                }
                if (! isset($voiced[$turn->termId])) {
                    $missingDays[$dialogue->dayIndex] = true;
                }
            }
        }
        if ($missingDays === []) {
            return;
        }

        $collections = [];
        foreach ($this->days->listForPlan($plan->id()) as $day) {
            $collectionId = $day->collectionId();
            if ($collectionId !== null && isset($missingDays[$day->dayIndex()])) {
                $collections[] = $collectionId;
            }
        }
        if ($collections !== []) {
            $this->speechOrders->order($collections);
        }
    }

    /**
     * Every day of this plan as a SCENE the situation builder can read.
     *
     * @return array<int, array{cards: list<SituationalCandidate>, skills: array<string, string>, intro: string|null, title: string|null}>
     */
    private function scenesOf(?PlanProgressView $progress): array
    {
        $out = [];
        foreach ($progress === null ? [] : $progress->days as $index => $day) {
            $out[$index] = [
                'cards' => PlanSittingPlanner::candidatesOf($day),
                'skills' => $day->skillOutcomes,
                'intro' => $day->sceneIntro,
                'title' => $day->sceneTitle,
            ];
        }

        return $out;
    }

    /**
     * Цепочка каждого дня, сплющенная до того, что нужно сборщику ситуации: сторона и id.
     *
     * По ДНЯМ, как и сцены, и по той же причине: карточка ступени B — это карточка ЧУЖОГО дня,
     * выданная в шве этого, и пара у неё своя.
     *
     * @param  list<PlanDialogueView>  $chains
     * @return array<int, list<SituationalTurn>>
     */
    private static function chainTurnsOf(array $chains): array
    {
        $out = [];
        foreach ($chains as $chain) {
            $out[$chain->dayIndex] = array_map(
                static fn (PlanDialogueTurnView $turn): SituationalTurn => new SituationalTurn(
                    $turn->turn,
                    $turn->termId,
                ),
                $chain->turns,
            );
        }

        return $out;
    }

    /**
     * КАНДИДАТЫ В ВАРИАНТЫ ХОДА [$termId], или null — эта карточка вариантов-реплик не получает.
     *
     * Null для всего, что не стоит на полках say/ask: слово, связка, число и реплика собеседника
     * собирают варианты по-своему. Null и когда план ещё не назвал сцены.
     *
     * УЖЕ СКАЗАННОЕ В ЭТОМ РАЗГОВОРЕ — не вариант (DAY-FIX-2, Ч.1.5): всё, что в цепочке сцены
     * стоит ДО этого хода, любой стороной, отдаётся правилу запретным списком.
     *
     * @param  array<int, array{cards: list<SituationalCandidate>, skills: mixed, intro: mixed, title: mixed}>  $scenes
     * @param  list<PlanDialogueView>  $chains
     * @return list<string>|null
     */
    private function answerPoolFor(string $termId, TermContentView $content, mixed $day, array $scenes, array $chains): ?array
    {
        if ($scenes === []) {
            return null;
        }

        // ВАРИАНТЫ ТАКТА «ЧТО ТЕБЕ СКАЗАЛИ?» (наряд DAY-FIX-3, Ч.2.2): реплики роли другой
        // функции, без перефразов правильной и друг друга; своя сцена первой, потом другие сцены
        // плана ({@see PlanHearOptions}). Функцию реплики роли знает цепочка — роль `ask`-пары
        // приглашает, остальные служат своему умению.
        if ($content->shelf === PlanHearOptions::SHELF_HEAR) {
            return PlanHearOptions::forLine(
                new SituationalCandidate($termId, $content->shelf, $content->skillRef, $content->text),
                array_map(static fn (array $scene): array => $scene['cards'], $scenes),
                $day === null ? null : (int) $day,
                self::roleFunctions($chains),
                self::saidBefore($termId, $day === null ? null : (int) $day, $chains),
            );
        }

        if (! PlanAnswerOptions::isSpokenShelf($content->shelf)) {
            return null;
        }

        return PlanAnswerOptions::forTurn(
            new SituationalCandidate($termId, $content->shelf, $content->skillRef, $content->text),
            array_map(static fn (array $scene): array => $scene['cards'], $scenes),
            $day === null ? null : (int) $day,
            self::saidBefore($termId, $day === null ? null : (int) $day, $chains),
        );
    }

    /**
     * THE FUNCTION OF EVERY ROLE LINE the sitting's conversations know: the role of an `ask` pair
     * is an INVITATION whatever its skill says, and two invitations are one function
     * ({@see PlanHearOptions::FUNCTION_INVITATION}). Every other role line answers by its skill.
     *
     * @param  list<PlanDialogueView>  $chains
     * @return array<string, string>
     */
    private static function roleFunctions(array $chains): array
    {
        $out = [];
        foreach ($chains as $chain) {
            foreach ($chain->turns as $turn) {
                if ($turn->turn === PlanDialogueMove::ROLE && $turn->pairKind === PlanDialogueMove::PAIR_ASK) {
                    $out[$turn->termId] = PlanHearOptions::FUNCTION_INVITATION;
                }
            }
        }

        return $out;
    }

    /**
     * Term ids of every turn of the scene's chain that stands BEFORE this one — what has already
     * been said in the conversation when the learner reaches this move.
     *
     * @param  list<PlanDialogueView>  $chains
     * @return list<string>
     */
    private static function saidBefore(string $termId, ?int $day, array $chains): array
    {
        foreach ($chains as $chain) {
            if ($chain->dayIndex !== $day) {
                continue;
            }
            $before = [];
            foreach ($chain->turns as $turn) {
                if ($turn->termId === $termId) {
                    return $before;
                }
                $before[] = $turn->termId;
            }
        }

        return [];
    }

    /**
     * WHICH PART OF THE SITTING a task belongs to — the key присесты are cut between: the section
     * code plus the DAY, exactly as the planner keys a spec ({@see PlanSittingPlanner::sectionKeyOfSpec()}).
     */
    private static function sectionKeyOf(PlanSessionTaskView $task): string
    {
        return $task->section === PlanSessionTaskView::SECTION_WARMUP
            ? PlanSessionSections::WARMUP
            : $task->sectionCode . '#' . ($task->fromDayIndex ?? 0);
    }

    // ── the strict session ───────────────────────────────────────────────────────────────────

    /** @return list<PlanSessionTaskView> */
    private function strictTasks(
        LearningPlan $plan,
        PlanProgressView $progress,
        int $dayIndex,
        PlanKnobs $knobs,
        PlanSittingLayout $layout,
    ): array {
        $today = $progress->days[$dayIndex] ?? null;
        if ($today === null) {
            return [];
        }

        // EVERY CARD OF THE PLAN, with its progress row — the whole input, read once. A card with
        // no view is dropped in assembly, which is how the whole warm-up and the whole seam went
        // missing on the stand while their standings said they were owed (С-2).
        $views = $this->planViews($plan, $progress);

        return $this->assembleTasks(
            $plan,
            $layout->specs,
            $views,
            $this->contentFor($progress, $views, $plan),
            $knobs,
            $dayIndex,
            $today->collectionId,
            progress: $progress,
            chains: $layout->chains,
        );
    }

    /**
     * THE FINAL DAY: the прогон of every scene, once, in order — PRACTICE in the one sense the word
     * has here: it schedules nothing, closes no stage and moves no focus.
     *
     * @return list<PlanSessionTaskView>
     */
    private function rehearsalTasks(
        LearningPlan $plan,
        PlanProgressView $progress,
        PlanKnobs $knobs,
        PlanSittingLayout $layout,
    ): array {
        $views = $this->planViews($plan, $progress);
        $content = [];
        foreach ($progress->days as $day) {
            $content += $day->content;
        }

        return $this->assembleTasks(
            $plan,
            $layout->specs,
            $views,
            $content,
            $knobs,
            // The day being studied is the FINAL one, and no card belongs to it — every one of
            // them came from a teaching day, so the seam count is zero.
            $progress->focusDayIndex,
            null,
            isPractice: true,
            progress: $progress,
            chains: $layout->chains,
        );
    }

    // ── assembly ─────────────────────────────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $specs
     * @param  array<string, DueTermView>  $views
     * @param  array<string, TermContentView>  $content
     * @param  int  $dayIndex  the day BEING STUDIED — what tells the day's own cards from the seam
     * @param  string|null  $langCollectionId  the collection a card's PAIR is read through
     * @param  list<PlanDialogueView>  $chains  the conversations the specs rest on
     * @return list<PlanSessionTaskView>
     */
    private function assembleTasks(
        LearningPlan $plan,
        array $specs,
        array $views,
        array $content,
        PlanKnobs $knobs,
        int $dayIndex,
        ?string $langCollectionId,
        bool $isPractice = false,
        ?PlanProgressView $progress = null,
        array $chains = [],
    ): array {
        $enabled = $this->enabledModes->forUser($plan->userId());
        $matrix = $this->admission->matrixFor($plan->userId());

        // EVERY DAY'S SCENE, flattened once — the input a situational card's position is assembled
        // from ({@see SituationalPrompt}). Per DAY and not per sitting: a card on stage B is a card
        // of an EARLIER day, dealt in the seam of this one, and its situation is its own scene's.
        $scenes = $this->scenesOf($progress);

        // …И ЦЕПОЧКА КАЖДОГО ДНЯ, сплющенная тем же движением: по ней сборщик ситуации находит
        // реплику, стоящую ПЕРЕД этим ходом, вместо того чтобы гадать по умению
        // ({@see SituationalPrompt}).
        $chainTurns = self::chainTurnsOf($chains);

        // THE OPTION POOL IS THE WHOLE PLAN, not today's folder.
        $poolIds = $this->planPoolIds($plan);

        // The far-option pool, exactly as the ordinary session builds it.
        $neighbours = [];
        $langs = $this->languages->forTerms(
            $plan->userId(),
            array_map(static fn (string $id): TermId => TermId::fromString($id), array_keys($content)),
            $langCollectionId,
        );
        foreach ($content as $termId => $view) {
            $neighbours[] = [
                'term_id' => $termId,
                'text' => $view->text,
                'translation' => $view->translation,
                'type' => $view->type,
                'kind' => $view->kind,
                'lang' => $view->lang,
                'support' => $langs->for($termId),
                'collections' => [],
            ];
        }

        $tasks = [];
        foreach ($specs as $index => $spec) {
            /** @var string $termId */
            $termId = $spec['term_id'];
            $view = $views[$termId] ?? null;
            $termContent = $content[$termId] ?? null;
            if ($view === null || $termContent === null) {
                continue;
            }

            /** @var ExerciseMode|null $mode */
            $mode = $spec['mode'] ?? null;
            // КЛАВИАТУРЫ В ПЛАНЕ НЕТ (канон §9, DAY-FIX-2 Ч.2.6). The ladder no longer names a
            // typed trainer; this is the lock under it, so a stray spec cannot put one on the
            // screen whatever wrote it.
            // (`intro` accepts no answer and says so with an exception, hence the guard.)
            if ($mode !== null && $mode !== ExerciseMode::Intro && $mode->forgivesTypos()) {
                continue;
            }

            // ВАРИАНТЫ ТВОЕГО ХОДА — реплики плана, которые человек мог бы сказать ВМЕСТО этой,
            // без уже сказанного в этом разговоре (наряд DAY-2-FIX Ч.1.5, DAY-FIX-2 Ч.1.5).
            $answerPool = $this->answerPoolFor($termId, $termContent, $spec['day'], $scenes, $chains);

            // СТРОГОСТЬ ХОДА, если это ход — decided by the planner, one rule for the card, the
            // chain and the census ({@see PlanTurnLevel::forTurn()}).
            $level = ($spec['turn_level'] ?? null) instanceof PlanTurnLevel ? $spec['turn_level'] : null;

            $card = $this->assembler->assemble(
                $plan->userId(), $view, $termContent, $poolIds, $enabled, $matrix,
                isPractice: $isPractice,
                cardIndex: $index,
                slotStep: $spec['step'],
                neighbours: $neighbours,
                modeOverride: $mode,
                supportLang: $langs->for($termId),
                // A WORD MET TODAY CHOOSES ITS TRANSLATION OUT OF FOUR (DAY-FIX-3, Ч.3.1) — the
                // planner says so on the spec; everything else takes the level's knob.
                optionCount: is_int($spec['options'] ?? null) ? max($knobs->mcOptions, $spec['options']) : $knobs->mcOptions,
                answerPoolIds: $answerPool,
                turnLevel: $level,
                // ЗНАКОМСТВО СОБИРАЕТСЯ ИЗ СВОИХ СЛОВ (наряд DAY-GATE-1, Ч.1.5). Ступень A на полке
                // реплики — это «Слова и фразы», первая встреча с фразой; чужие блоки там не выбор,
                // а помеха. В разговоре они остаются.
                ownBlocksOnly: ($spec['stage'] ?? null) === PlanStage::A,
            );
            // The assembler refused this card — the term's data could not build it after all, and on
            // the strict path there is nothing to fall back to on purpose.
            if ($card === null) {
                continue;
            }
            // A CHOICE THAT CAME OUT AS BLOCKS — the plan's pool could not furnish the floor and the
            // assembler fell back to assembly (DAY-FIX-2, Ч.1.7) — is reported as what it IS, so the
            // caption over the card and the day screen's word say «соберёшь», not «выберешь».
            if ($level === PlanTurnLevel::Choose && $card->chips !== null) {
                $level = PlanTurnLevel::Assemble;
            }

            /** @var PlanStage|null $stage */
            $stage = $spec['stage'];
            $dealt = ExerciseMode::from($card->exerciseMode);
            // THE CARD'S OWN DAY, not the day being studied.
            $scene = $scenes[$spec['day'] === null ? -1 : (int) $spec['day']] ?? null;
            $situation = $scene === null ? null : $this->situations->for(
                $dealt,
                new SituationalCandidate($termId, $termContent->shelf, $termContent->skillRef, $termContent->text),
                $scene['cards'],
                $scene['skills'],
                $scene['intro'],
                $scene['title'],
                $chainTurns[$spec['day'] === null ? -1 : (int) $spec['day']] ?? [],
            );

            $tasks[] = new PlanSessionTaskView(
                card: $card,
                stage: $stage?->value,
                ordinal: (int) $spec['ordinal'],
                ofSteps: (int) $spec['of'],
                fromDayIndex: $spec['day'] === null ? null : (int) $spec['day'],
                softened: (bool) $spec['softened'],
                source: (string) $spec['source'],
                // THE SEAM, named on the server: today's is the day, an earlier one is «Повторение».
                section: is_string($spec['section'] ?? null)
                    ? $spec['section']
                    : ((int) $spec['day'] === $dayIndex
                        ? PlanSessionTaskView::SECTION_DAY
                        : PlanSessionTaskView::SECTION_REVIEW),
                origin: null,
                speakingForm: $dealt === ExerciseMode::Speaking
                    ? $stage?->speakingForm($termContent->kind ?? PlanStageLadder::KIND_WORD)
                    : null,
                clozeSource: null,
                knobsApplied: PlanKnobSupport::appliedTo($dealt),
                knobsIgnored: PlanKnobSupport::ignoredBy($dealt),
                speaker: $termContent->speaker,
                kind: $termContent->kind,
                shelf: $termContent->shelf,
                tier: $termContent->tier,
                situation: $situation?->toArray(),
                speaksAfterChoice: $dealt->speaksAfterChoice(),
                sectionCode: is_string($spec['section_code'] ?? null)
                    ? $spec['section_code']
                    : PlanSessionSections::REHEARSAL,
                turnLevel: match (true) {
                    $level !== null && ($dealt->speaksAfterChoice() || $level === PlanTurnLevel::Say) => $level->value,
                    default => null,
                },
                // ЧТО ИМЕННО НАДО СКАЗАТЬ — только на сборке, и по факту КАРТОЧКИ, а не спека:
                // выбор, откатившийся в сборку из-за голодного пула (DAY-FIX-2, Ч.1.7), — это тоже
                // сборка, и человек имеет право знать, что от него хотят.
                intent: $card->chips !== null && $dealt->isSituational() ? $termContent->translation : null,
            );
        }

        return $tasks;
    }

    // ── the pieces ───────────────────────────────────────────────────────────────────────────

    /**
     * EVERY TERM THIS PLAN STANDS ON, all days — the pool a plan card's wrong answers come from.
     *
     * @return list<string>
     */
    private function planPoolIds(LearningPlan $plan): array
    {
        $ids = [];
        foreach ($this->days->listForPlan($plan->id()) as $day) {
            $collectionId = $day->collectionId()?->value;
            if ($collectionId === null) {
                continue;
            }
            foreach ($this->collectionTerms->termIdsForCollection($plan->userId(), $collectionId, self::DUE_CAP) as $termId) {
                $ids[$termId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * THE PROGRESS ROW OF EVERY CARD THE PLAN STANDS ON, all days at once — one read.
     *
     * A card with no progress row at all is {@see DueTermView::outOfPool()} — the same answer
     * free practice fills its gaps with. NUMBERS ARE NOT HERE: {@see PlanProgress} leaves them out
     * of `termIds` because no session deals them yet (канон §6).
     *
     * @return array<string, DueTermView>
     */
    private function planViews(LearningPlan $plan, PlanProgressView $progress): array
    {
        $ids = [];
        foreach ($progress->days as $day) {
            foreach ($day->termIds as $termId) {
                $ids[$termId] = true;
            }
        }
        $ids = array_keys($ids);
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($this->dueInPlan->allInScope($plan->userId(), $ids, count($ids)) as $view) {
            $out[$view->termId->value] = $view;
        }
        foreach ($ids as $termId) {
            $out[$termId] ??= DueTermView::outOfPool(TermId::fromString($termId));
        }

        return $out;
    }

    /**
     * Content for every term the session might deal — the plan's own (already hydrated per day,
     * each through its own collection) plus whatever else the planner made due.
     *
     * @param  array<string, DueTermView>  $views
     * @return array<string, TermContentView>
     */
    private function contentFor(PlanProgressView $progress, array $views, LearningPlan $plan): array
    {
        $content = [];
        foreach ($progress->days as $day) {
            $content += $day->content;
        }

        $missing = array_values(array_diff(array_keys($views), array_keys($content)));
        if ($missing === []) {
            return $content;
        }

        $ids = array_map(static fn (string $id): TermId => TermId::fromString($id), $missing);

        return $content + $this->content->byIds($ids, $this->languages->forTerms($plan->userId(), $ids));
    }

    /** @param list<PlanDay> $days */
    private function dayAt(array $days, int $index): ?PlanDay
    {
        foreach ($days as $day) {
            if ($day->dayIndex() === $index) {
                return $day;
            }
        }

        return null;
    }

    /** @param list<PlanSessionTaskView> $tasks */
    private function persist(StudySessionId $sessionId, LearningPlan $plan, PlanDay $day, array $tasks, bool $strict): void
    {
        /** @var list<TermId> $composition */
        $composition = [];
        $seen = [];
        foreach ($tasks as $task) {
            if (isset($seen[$task->card->termId])) {
                continue;
            }
            $seen[$task->card->termId] = true;
            $composition[] = TermId::fromString($task->card->termId);
        }

        $now = $this->clock->now();
        $this->tx->run(function () use ($sessionId, $plan, $day, $composition, $strict, $now): void {
            $this->sessions->save(StudySession::start(
                id: $sessionId,
                userId: $plan->userId(),
                // The run-through IS practice: it never schedules and never advances anything.
                isPractice: ! $strict,
                composition: $composition,
                startedAt: $now,
                collectionId: $day->collectionId() === null ? null : CollectionId::fromString($day->collectionId()->value),
            ));
        });
    }
}
