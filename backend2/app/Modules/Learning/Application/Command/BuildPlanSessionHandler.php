<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Learning\Application\Dto\PlanSessionView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\HomePlanReader;
use App\Modules\Learning\Application\Port\ModeAdmissionReader;
use App\Modules\Learning\Application\Port\OrdersLineSpeech;
use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
use App\Modules\Learning\Application\Service\CardLanguageResolver;
use App\Modules\Learning\Application\Service\PlanDayPassing;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Application\Service\PlanSceneTurns;
use App\Modules\Learning\Application\Service\StudyCardAssembler;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Application\Dto\PlanDialogueTurnView;
use App\Modules\Learning\Application\Dto\PlanLineAudioView;
use App\Modules\Learning\Application\Dto\PlanDialogueView;
use App\Modules\Learning\Application\Service\LineAudioIndex;
use App\Modules\Learning\Domain\Service\PlanAnswerOptions;
use App\Modules\Learning\Domain\Service\PlanDayOrder;
use App\Modules\Learning\Domain\Repository\PlanSceneRunRepository;
use App\Modules\Learning\Domain\Repository\PlanTermStageRepository;
use App\Modules\Learning\Domain\Service\PlanDialogueChain;
use App\Modules\Learning\Domain\Service\PlanDialogueLevel;
use App\Modules\Learning\Domain\Service\PlanGenerationPolicy;
use App\Modules\Learning\Domain\Service\PlanSceneRunGate;
use App\Modules\Learning\Domain\Service\PlanKnobSupport;
use App\Modules\Learning\Domain\Service\PlanSessionSections;
use App\Modules\Learning\Domain\Service\PlanSittings;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\Service\SituationalPrompt;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanDayCard;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Learning\Domain\ValueObject\PlanTurnLevel;
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
 * the STAGE decides the trainers, so what this handler does is lay out a running order and force a
 * mode onto every slot. Everything below the mode — the card, the options, the grading, the offline
 * check — is the app's ordinary machinery, untouched, which is the whole reason a plan day owns an
 * ordinary collection.
 *
 * ## A SITTING IS MADE OF THIS PLAN'S CARDS AND OF NOTHING ELSE
 *
 * Two buckets, in the order they are dealt:
 *
 *   1. **new** — this day's words that have not closed stage A, in the order A2 computed
 *      ({@see PlanDayOrder}): the pieces, then the connectors, then the replies built out of them,
 *      then the interlocutor's own line. Each brings its whole remaining stage-A checklist, because
 *      stage A has to close in ONE sitting or the day does not pass.
 *   2. **plan reviews** — words of this plan, introduced on ANOTHER day, that THE PLAN'S OWN LADDER
 *      owes a card today: stage A closed, a night passed, so the standing stands on B or C with a
 *      `nextMode`. B before C, because a word two stages from ready needs the sitting more than one
 *      that is nearly there. This is the seam the learner reads as «Повторение · из прошлых дней».
 *
 *      IT IS THE LADDER AND NOT `due_at`, and that sentence is the whole of E2E-SIM-2 С-2. The seam
 *      used to be assembled from {@see DueTermsReader::selectableForPlan()} — the plan's own words
 *      that the REPETITION PLANNER had made due — while the STAGE was computed from the plan's
 *      ladder. The two agree only while SM-2's interval happens to be shorter than the plan, and
 *      they part company on the second night of any plan: measured on the stand (Э4.2), every one of
 *      day 1's sixteen cards stood on stage B with a `nextMode`, and not one of them was dealt,
 *      because their `due_at` had gone out to 2026-10-21…2027-01-09. The rescue kit went the same
 *      way — out to 2027 — so «спасатели каждый день до конца плана» quietly stopped after two days.
 *      A plan deals BY THE PLAN; `due_at` goes on being written by the ordinary scheduler and goes
 *      on being read the day the plan ends and its words return to the pool.
 *
 * ## There is no third bucket, and «no» here means NOT CALLED
 *
 * There used to be: whatever else the planner had due, poured in behind the day so that «a plan does
 * not suspend the rest of the learner's vocabulary». Two live runs said what that is actually worth.
 * First, French cards and another plan's lines inside an `ru→en` lesson — answered on 31.08 with a
 * language-pair filter (DECISIONS п. 204). Then, on 01.09 with that filter in place: «Аренда жилья»,
 * day 1, task 47 of 67, «Повторение · из плана: Отдых в Италии», the word «паспорт» to be recognised
 * among `utilities`, `available`, `deposit` — every one of them the learner's own, in the right pair,
 * and every one of them out of a lesson this lesson is not.
 *
 * The rule is cancelled rather than tightened. A filter is a promise that somebody remembered every
 * way a foreign card can look like a local one, and two rounds of that promise were broken by cards
 * nobody had thought of. So the read itself is scoped ({@see planViews()}): the session cannot deal
 * a word this plan did not enrol, because the only term ids it ever asks about are the ones its own
 * days hold.
 *
 * The other direction is the same rule seen from the pool: a plan's words are dealt BY THE PLAN and
 * by nothing else — out of the ordinary session and out of «Повторить N»
 * ({@see \App\Modules\Learning\Infrastructure\Eloquent\PlanHeldTerms}) while it runs, and out of
 * them for good when it ends, because an ended plan is an ARCHIVE and its words are added back to
 * «Учить» by the learner or not at all.
 *
 * The order of the two IS the contract: the day, then the seam. `day_task_count` on the payload says
 * where the boundary falls and every task carries its own {@see PlanSessionTaskView::$section} — and
 * both now count the day's own cards alone, which is why day 1 of any plan is «N из N» with no
 * «Повторение» above it.
 *
 * ## «Когда» is not this handler's business
 *
 * Nothing here reads or writes `due_at`, an interval or an ease. The plan says WHICH card; the
 * repetition planner says WHEN a word comes back, and it learns that from the review log like it
 * always has — a stage B success is an ordinary review row, and SM-2 reads it without knowing
 * plans exist.
 *
 * ## A day opened ahead of the focus
 *
 * STRICT, like every other day of the plan, and this is the second half of E2E-SIM-2 (С-1).
 *
 * It used to be a «мягкий прогон»: the day's collection, shuffled, one card each, no stages, mode
 * chosen by the ORDINARY selector. The learner may look at day 3 on day 1 — the material exists and
 * refusing to show it would be pretending it does not — but what they were shown was not the day. It
 * was an exam before the introduction: measured on the stand (Э3.2) and again on the owner's live
 * evening (03.09), seventeen tasks with no `intro` among them, six of them dictations of a sentence
 * the learner had never been shown, the running order shuffled against канон §11, and cards of the
 * `numbers` shelf that no session is supposed to deal at all. The screen captioned it «СТУПЕНЬ A ·
 * ПОВТОРЕНИЕ» over material that had neither.
 *
 * So a day opened early is dealt exactly what it would be dealt in its turn — its own cards, at
 * whatever rung the plan's ladder stands them on, in канон §11's order, the intro first and once.
 * Since none of those cards has been introduced, that is stage A and nothing else: the железное
 * правило «B не раньше закрытой A + ночи» is what makes «only stage A» a CONSEQUENCE here rather
 * than a second rule to keep in step.
 *
 * Two things it does not get: the seam (an early day is not a day whose earlier days are being
 * revised — the learner came to look ahead, and «Повторение» over material from tomorrow's lesson is
 * an answer to a question nobody asked), and any special treatment of the focus, which moves when
 * days PASS and therefore moves on its own if the learner actually finishes what they opened.
 */
final readonly class BuildPlanSessionHandler
{
    /**
     * Seconds a card costs when this learner has no measured figure yet.
     *
     * SIXTEEN, measured, and it used to be eight «because that is the home screen's number». Eight
     * was never a measurement of anything: at eight seconds a card, twenty minutes buys 150 cards
     * and a day-scene of 68–81 fits in one присест, which is why the «присест пройден» screen never
     * appeared once in the whole E2E-SIM-2 run (С-12) — the mechanism was right and there was
     * nothing to switch it on. The owner's own live day 1 is the figure used here: 60 answers in
     * 962 seconds ≈ 16 s a card, the first sitting of a plan, with the reading and the audio in it.
     *
     * It is a DEFAULT and it is replaced the moment the learner has fifty answers of their own
     * ({@see taskBudget()}), so a fast reader is measured and not assumed — the number only has to
     * be honest about somebody who has never sat down yet.
     */
    private const DEFAULT_CARD_SECONDS = 16;

    /** How many recent answers the per-learner card-seconds figure is measured over. */
    private const LATENCY_SAMPLE = 50;

    /**
     * The ceiling on ONE PAYLOAD — a sanity bound, not a teaching rule.
     *
     * It used to be «a session is a sitting, not a marathon», which is what the budget said too. The
     * two questions came apart in Ч-6: the minutes are the sitting now, and this is only the point
     * past which a single response has stopped being a lesson and become a data dump.
     */
    private const MAX_TASKS = 120;

    /** Bound on the due read: the same order of magnitude as the ordinary session's. */
    private const DUE_CAP = 100;

    /**
     * How many of yesterday's misses the warm-up carries (канон §5, разогрев v2).
     *
     * Five, beside the five rescue phrases, because the warm-up is TWO MINUTES: ten light touches
     * is what fits, and a warm-up that grows with a bad evening is a punishment for having had one.
     */
    private const WARMUP_MISS_CAP = 5;

    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
        private PlanModeSettingsReader $planSettings,
        /**
         * THE PROGRESS ROWS OF THIS PLAN'S OWN CARDS — read by term id, never by `due_at`.
         *
         * It is a reader of rows and not a queue any more. It used to answer «which of this plan's
         * words has the repetition planner made due» ({@see DueTermsReader::selectableForPlan()}),
         * which is how the seam was chosen until С-2 showed the two ideas of «owed» drifting apart
         * on the second night of every plan. The plan asks the LADDER what is owed and asks this
         * only for the row the card is built from ({@see planViews()}).
         */
        private DueTermsReader $dueInPlan,
        private UserCollectionTermsReader $collectionTerms,
        private TermContentReader $content,
        private CardLanguageResolver $languages,
        private StudyCardAssembler $assembler,
        private EnabledModesReader $enabledModes,
        private ModeAdmissionReader $admission,
        private HomePlanReader $home,
        private StudySessionRepository $sessions,
        private TransactionManager $tx,
        private Clock $clock,
        /**
         * «День пройден», written by the same code that writes it when a sitting ENDS
         * ({@see PlanDayPassing}). It used to be a private method here, which is why a day the
         * learner had just finished stayed `ready` until they opened the next session.
         */
        private PlanDayPassing $passing,
        /**
         * КАКИЕ РЕПЛИКИ УЖЕ ЗВУЧАТ ФАЙЛОМ (наряд TTS-1). Не nullable и без дефолта: пустой ответ —
         * законное состояние (труба выключена, файлы не догнали), а вот «сборка не спросила» — нет,
         * и необязательная зависимость сделала бы эти два случая неразличимыми.
         */
        private LineAudioIndex $lineAudio,
        private OrdersLineSpeech $speechOrders,
        /**
         * НА КАКОМ УРОВНЕ СТРОГОСТИ СТОИТ КАЖДЫЙ ХОД ЭТОГО ПЛАНА (наряд SCENE-RUN, Ч.1).
         *
         * Один запрос на посадку, планом целиком: уровень нужен и задаче, и цепочке, а цепочка
         * длиннее списка задач — экран рисует ленту вперёд и должен знать, чем ход станет.
         */
        private PlanTermStageRepository $termStages,
        /**
         * ЧТО УЖЕ ПРОГОНЯЛИ. Спрашивается один раз на посадку: прогон — событие, и сцена, которую
         * сегодня уже прогнали, второй раз за вечер мерила бы память о первом прогоне.
         */
        private PlanSceneRunRepository $sceneRuns,
        /** Какие ходы сцены — твои. Один ответ на всех, кто спрашивает ({@see PlanSceneTurns}). */
        private PlanSceneTurns $sceneTurns,
        private PlanDayOrder $order = new PlanDayOrder(),
        /** Where a situational card's «Ситуация» comes from — pure, and stated in Domain. */
        private SituationalPrompt $situations = new SituationalPrompt(),
        /**
         * THE ORDER A SCENE IS SPOKEN IN — the day's own chain since P2 v0.5, and the shelves
         * paired by `skill_ref` for every day written before it (наряд DAY-2).
         */
        private PlanDialogueChain $dialogues = new PlanDialogueChain(),
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

        // EVERY TEACHING DAY IS STRICT — its turn or not. See the class docblock, «A day opened
        // ahead of the focus»: the soft run is gone, and it is gone rather than gated, because a
        // second way to deal a plan day is a second place for «B без A» to be invented.
        $strict = $day->kind() === PlanDayKind::Intro;
        $knobs = $this->planSettings->knobsFor($plan->level());

        [$tasks, $dialogues] = $strict
            ? $this->strictTasks($plan, $progress, $dayIndex, $knobs, $this->termStages->forPlan($plan->id()))
            // THE FINAL DAY IS A RUN-THROUGH, not a lesson (Д-27). It introduces nothing and owns no
            // collection, which is why asking to GENERATE it is a 404 — there is no material to
            // buy. The material already exists: it is every card the plan has taught. Before this
            // the client asked for a build, got the 404 and dead-ended, so the plan could not be
            // finished from the app at all and the live run closed it from tinker.
            // A RUN-THROUGH HAS NO CONVERSATION. Every card of the plan, once each, in the order it
            // was taught — that is a different shape from a scene being spoken, and the dialogue of
            // the прогон is SCENE-RUN's, not this one's.
            : $this->rehearsalTasks($plan, $progress, $knobs, $this->termStages->forPlan($plan->id()));

        $sessionId = $command->sessionId ?? StudySessionId::generate();
        $this->persist($sessionId, $plan, $day, $tasks, $strict);

        $audio = $this->lineAudioFor($tasks, $dialogues, $plan->targetLang()->value);
        // …И ЗАКАЗАТЬ ТО, ЧЕГО НЕ ХВАТИЛО. «Готовим озвучку» — состояние на секунды, а не навсегда
        // (канон §7): реплика, у которой файла нет и заказа не было, держала бы экран-диалог в
        // ожидании вечно. Посадка видит недостачу первой и единственная — она ищет адреса ровно
        // перед тем, как человек эти реплики услышит.
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
            // WHERE IT IS HONEST TO STOP. Computed from the tasks that actually survived assembly,
            // for the same reason `dayTaskCount` is: a spec whose card the assembler refused is not
            // a card the learner will sit through.
            sittings: PlanSittings::cut(array_map(self::sectionKeyOf(...), $tasks), $this->taskBudget($plan)),
            // THE CONVERSATIONS, in the order the sitting reaches them — the dialogue screen's whole
            // input beside the tasks themselves.
            dialogues: self::dialoguesWithAudio($dialogues, $audio),
            // ОЗВУЧКА (наряд TTS-1): всё, что эта посадка может сыграть файлом, — одним списком,
            // чтобы телефон скачал её ЦЕЛИКОМ на входе в день, а не по мере того, как доходит до
            // карточки. Реплики второго присеста готовы к его началу по этой же причине.
            lineAudio: $audio,
        );
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
            ), $d->turns),
        ), $dialogues);
    }

    /**
     * ЧТО ИЗ ЭТОЙ ПОСАДКИ УЖЕ ЗВУЧИТ ФАЙЛОМ — карточки задач и ходы диалогов, спрошенные одним
     * запросом.
     *
     * Оба источника, а не один: ход цепочки может не иметь сегодня своей задачи (диалог играется
     * целиком, задач меньше), а спасатель разогрева не стоит ни в одной цепочке. Спросить только
     * задачи значило бы оставить без голоса реплику, которая всё равно прозвучит.
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
     * Считается по ЦЕПОЧКАМ, а не по задачам: экран-диалог играет разговор целиком, и молчащий ход
     * — это ход, у которого сегодня своей карточки может и не быть. Полки — те же две, что
     * озвучивает станок ({@see \App\Modules\Generation\Application\Command\SpeakCollectionLinesHandler}):
     * «Тебе скажут» и спасатели. Ярус «говорю» файлом не читается вовсе — там говорит человек.
     *
     * Ничего не ждёт и ничего не ломает: заказ идемпотентен (покупается только то, чего нет для
     * ЭТОГО голоса), тумблер трубы стоит в диспетчере, а посадка отдаётся клиенту в любом случае —
     * с теми адресами, которые уже есть.
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
                if (! in_array($turn->shelf, [PlanDialogueChain::SHELF_HEAR, self::SHELF_RESCUE], true)) {
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

        // Заказывается КОЛЛЕКЦИЯ дня целиком, а не отдельная реплика: станок работает полкой и сам
        // отбирает недостающее, а список из одной строки заставил бы его завести второй путь.
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
     * Every day of this plan as a SCENE the situation builder can read — its вводка, its name, its
     * abilities, and its own cards flattened to what a pairing needs.
     *
     * Built once per sitting rather than per card: a day of a plan is a couple of dozen cards and a
     * sitting deals every one of them, so this is one pass instead of one pass each.
     *
     * @return array<int, array{cards: list<SituationalCandidate>, skills: array<string, string>, intro: string|null, title: string|null}>
     */
    private function scenesOf(?PlanProgressView $progress): array
    {
        $out = [];
        foreach ($progress === null ? [] : $progress->days as $index => $day) {
            $cards = [];
            foreach ($day->content as $termId => $view) {
                $cards[] = new SituationalCandidate($termId, $view->shelf, $view->skillRef, $view->text);
            }
            $out[$index] = [
                'cards' => $cards,
                'skills' => $day->skillOutcomes,
                'intro' => $day->sceneIntro,
                'title' => $day->sceneTitle,
            ];
        }

        return $out;
    }

    /**
     * КАНДИДАТЫ В ВАРИАНТЫ ХОДА [$termId], или null — эта карточка вариантов-реплик не получает.
     *
     * Null для всего, что не стоит на полках say/ask: слово, связка, число и реплика собеседника
     * собирают варианты по-своему, и подмена пула сломала бы им карточку. Null и когда план ещё не
     * назвал сцены (мягкий прогон, прогон перед событием) — там ситуационных карточек не бывает.
     *
     * @param  array<int, array{cards: list<SituationalCandidate>, skills: mixed, intro: mixed, title: mixed}>  $scenes
     * @return list<string>|null
     */
    private function answerPoolFor(string $termId, TermContentView $content, mixed $day, array $scenes): ?array
    {
        if ($scenes === [] || ! PlanAnswerOptions::isSpokenShelf($content->shelf)) {
            return null;
        }

        return PlanAnswerOptions::forTurn(
            new SituationalCandidate($termId, $content->shelf, $content->skillRef, $content->text),
            array_map(static fn (array $scene): array => $scene['cards'], $scenes),
            $day === null ? null : (int) $day,
        );
    }

    /**
     * WHICH PART OF THE SITTING a task belongs to — the key присесты are cut between.
     *
     * The task already carries its own part as a code ({@see PlanSessionTaskView::$sectionCode}) and
     * this is that code plus the DAY it belongs to. The day matters because the seam can hold two
     * scenes at once, and two conversations running into each other with no break between them is
     * exactly the cut the learner needs: «Диалог · сцена 1» ends, «Диалог · сцена 2» begins.
     *
     * Kept on the server rather than derived on the wire for the reason it always was: it is the
     * same grouping the client draws its captions from, and two names for one grouping is how the
     * two drift.
     */
    private static function sectionKeyOf(PlanSessionTaskView $task): string
    {
        return $task->section === PlanSessionTaskView::SECTION_WARMUP
            ? PlanSessionSections::WARMUP
            : $task->sectionCode . '#' . ($task->fromDayIndex ?? 0);
    }

    /**
     * WHAT THE LEARNER IS DOING with this card — the part of the sitting it belongs to, as a code.
     *
     * The shelf answers most of it; the RUNG answers the rest, and that is the whole of DAY-2 in one
     * expression: a `say` card at stage A is being MET («знакомство с репликами сцены») and the same
     * card at stage B is being SPOKEN («диалог сцены»). Nothing but the stage tells those apart, and
     * the client used to be handed only the shelf.
     *
     * @param  array<string, mixed>  $spec
     */
    private function sectionCodeOf(array $spec, PlanProgressView $progress): string
    {
        if (($spec['section'] ?? null) === PlanSessionTaskView::SECTION_WARMUP) {
            return PlanSessionSections::WARMUP;
        }

        /** @var PlanStage|null $stage */
        $stage = $spec['stage'] ?? null;
        /** @var string $termId */
        $termId = $spec['term_id'];

        return PlanSessionSections::ofShelf(self::shelfOf($progress, $termId), $stage?->value);
    }

    /** `terms.shelf` for a card of this plan, read off the progress the sitting was built from. */
    private static function shelfOf(PlanProgressView $progress, string $termId): ?string
    {
        foreach ($progress->days as $day) {
            $content = $day->content[$termId] ?? null;
            if ($content !== null) {
                return $content->shelf;
            }
        }

        return null;
    }

    /**
     * THE RUNNING ORDER OF A SITTING — канон §10, applied to specs that already exist.
     *
     * «Разогрев → слова и связки → знакомство с репликами сцены → диалог сцены → цифры → прогон»,
     * and then the seam, which is the same list again over an earlier scene. Four keys, in this
     * order and no other:
     *
     *   1. the warm-up first, always, and it is not sorted with the rest — it is not a part of any
     *      scene, it is the five phrases that keep every scene alive (канон §5);
     *   2. THE DAY BEFORE THE SEAM. PLAN-FIX-7's rule, unchanged: a section announced after the
     *      cards it labels is not a section;
     *   3. the part, in канон order;
     *   4. inside a `dialogue` part, THE SCENE'S OWN CHAIN — because an exchange is the unit and a
     *      conversation dealt in shelf order is not a conversation.
     *
     * Stable throughout ({@see usort} is stable in PHP 8), so cards that tie on all four keep the
     * order the buckets built them in — which for a scene's introduction is {@see PlanDayOrder}'s,
     * i.e. канон §11's.
     *
     * @param  list<array<string, mixed>>  $specs
     * @param  array<int, PlanDialogueView>  $chains  day index => that scene's conversation
     * @return list<array<string, mixed>>  the same specs, ordered, each stamped with `section_code`
     */
    private function ordered(array $specs, PlanProgressView $progress, array $chains, int $dayIndex): array
    {
        // WHERE A CARD STANDS IN ITS SCENE'S CONVERSATION — «day 2, turn 5». Cards outside every
        // chain keep a position past the end of it, so they follow the conversation rather than
        // breaking into it.
        $atTurn = [];
        foreach ($chains as $day => $chain) {
            foreach ($chain->turns as $position => $turn) {
                $atTurn[$day . '#' . $turn->termId] ??= $position;
            }
        }

        $warmup = [];
        $rest = [];
        foreach ($specs as $position => $spec) {
            // Ведро, которое НАЗВАЛО свою часть, знает о ней больше правила: прогон сцены играется
            // тем же тренажёром и на той же полке, что и говорение фразы, и вывести его из полки со
            // ступенью нельзя — различает их то, зачем карточку раздали.
            $spec['section_code'] = is_string($spec['section_code'] ?? null)
                ? $spec['section_code']
                : $this->sectionCodeOf($spec, $progress);
            if ($spec['section_code'] === PlanSessionSections::WARMUP) {
                $warmup[] = $spec;

                continue;
            }

            $day = $spec['day'] === null ? -1 : (int) $spec['day'];
            $rest[] = [
                'spec' => $spec,
                'key' => [
                    // The day being studied, then everything behind it — the seam. ПРОГОН СЦЕНЫ
                    // стоит на стороне ДНЯ, чьей бы сцены он ни был: это сегодняшний шаг лестницы,
                    // а не возврат к пройденному, и подпись шва над ним была бы неправдой.
                    $day === $dayIndex || $spec['section_code'] === PlanSessionSections::SCENE_RUN ? 0 : 1,
                    PlanSessionSections::rankOf($spec['section_code']),
                    $day,
                    $atTurn[$day . '#' . $spec['term_id']] ?? PHP_INT_MAX,
                    $position,
                ],
            ];
        }

        usort($rest, static fn (array $a, array $b): int => $a['key'] <=> $b['key']);

        return [...$warmup, ...array_map(static fn (array $row): array => $row['spec'], $rest)];
    }

    /**
     * THE CONVERSATIONS THIS SITTING PLAYS — one per scene that has a `dialogue` part in it.
     *
     * Built for the scenes the sitting actually reaches and no others: a chain the learner will not
     * be handed a move in is a conversation nobody opens, and shipping every day's would put the
     * whole plan on the wire on the morning of day 9.
     *
     * @param  list<array<string, mixed>>  $specs  already stamped with `section_code`
     * @param  array<string, PlanTermStage>  $stages  what each pair has proved — {@see turnLevelFor()}
     * @return array<int, PlanDialogueView>  day index => that scene's conversation
     */
    private function chainsFor(array $specs, PlanProgressView $progress, array $stages): array
    {
        $wanted = [];
        foreach ($specs as $spec) {
            // Разговор нужен ОБЕИМ секциям, которые его играют: диалогу (ступень B) и прогону
            // (ступень C). Лента прогона — та же цепочка, и без неё экран не знал бы, что говорит
            // собеседник между твоими ходами.
            $code = $spec['section_code'] ?? null;
            if (($code === PlanSessionSections::DIALOGUE || $code === PlanSessionSections::SCENE_RUN)
                && $spec['day'] !== null) {
                $wanted[(int) $spec['day']] = true;
            }
        }

        $out = [];
        foreach (array_keys($wanted) as $index) {
            $day = $progress->days[$index] ?? null;
            if ($day === null) {
                continue;
            }

            $cards = [];
            foreach ($day->content as $termId => $view) {
                $cards[] = new SituationalCandidate($termId, $view->shelf, $view->skillRef, $view->text);
            }

            $turns = [];
            foreach ($this->dialogues->for($day->dialogue, $cards) as $move) {
                $content = $day->content[$move->termId] ?? null;
                if ($content === null) {
                    continue;
                }
                $turns[] = new PlanDialogueTurnView(
                    turn: $move->turn,
                    termId: $move->termId,
                    text: $content->text,
                    translation: $content->translation,
                    shelf: $content->shelf,
                    // Уровень строгости — на КАЖДОМ своём ходу цепочки, а не только на тех, у
                    // которых сегодня есть задача: экран рисует ленту вперёд, и ход, до которого
                    // лестница ещё не дошла, должен выглядеть тем, чем он станет.
                    level: $move->isRole() ? null : self::turnLevelFor($move->termId, $stages)->value,
                );
            }

            if ($turns !== []) {
                $out[$index] = new PlanDialogueView(
                    dayIndex: $index,
                    sceneTitle: $day->sceneTitle,
                    sceneIntro: $day->sceneIntro,
                    turns: $turns,
                    // Дозрела ли сцена до прогона. В обычный день у сцены, чей прогон собрали, это
                    // всегда true; на последнем дне гоняются и недозревшие, и итог их помечает.
                    runReady: $this->sceneTurnsOf($day)[1],
                );
            }
        }

        return $out;
    }

    /**
     * СТРОГОСТЬ ХОДА ЭТОЙ РЕПЛИКИ — выбор, пока пара не закрыла выбор без ошибок дважды.
     *
     * Пара, о которой строки ещё нет, стоит на выборе: счётчик по умолчанию ноль, и это верное
     * прочтение — «ничего ещё не доказано» и «доказано ноль раз» здесь одно и то же.
     *
     * @param  array<string, PlanTermStage>  $stages
     */
    private static function turnLevelFor(string $termId, array $stages): PlanTurnLevel
    {
        return PlanDialogueLevel::forStreak(self::streakOf($termId, $stages));
    }

    /**
     * Безошибочных выборов подряд у этой пары — ноль у пары, о которой строки ещё нет.
     *
     * @param  array<string, PlanTermStage>  $stages
     */
    private static function streakOf(string $termId, array $stages): int
    {
        $stage = $stages[$termId] ?? null;

        return $stage === null ? 0 : $stage->choiceStreak;
    }

    // ── the strict session ───────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, PlanTermStage>  $stages
     * @return array{0: list<PlanSessionTaskView>, 1: list<PlanDialogueView>}
     */
    private function strictTasks(
        LearningPlan $plan,
        PlanProgressView $progress,
        int $dayIndex,
        PlanKnobs $knobs,
        array $stages,
    ): array {
        $today = $progress->days[$dayIndex] ?? null;
        if ($today === null) {
            return [[], []];
        }

        // WHETHER THIS IS THE DAY THE PLAN IS ON. It decides one thing and one thing only — whether
        // the sitting carries a seam — and everything else about the sitting is identical, which is
        // the point of «мягкого прогона больше нет».
        $isFocusDay = $dayIndex === $progress->focusDayIndex;

        // EVERY CARD OF THE PLAN, with its progress row — the whole input, read once.
        //
        // Not «the due ones plus today's»: a card of an earlier day owes its stage-B card because
        // the LADDER says so, and a card with no view is dropped in assembly, which is how the whole
        // warm-up and the whole seam went missing on the stand while their standings said they were
        // owed (С-2).
        $views = $this->planViews($plan, $progress);

        $specs = [];
        $taken = [];

        // 0. THE WARM-UP — the rescue kit, ONCE A DAY, before anything else (канон §5).
        //
        // «Тренируются жёстче всех: разогрев ~2 минуты каждый день до конца плана, из ротации не
        // выпадают.» They are the five phrases that keep a conversation alive when it breaks, so
        // they are the one thing that must not wait its turn in a queue: a learner who never gets
        // to «Помедленнее, пожалуйста» is a learner who stops at the first sentence they miss.
        //
        // FIRST, and that is the section's whole meaning — a seam announced after the cards it
        // labels is not a seam. The day's own material follows it, and `day_task_count` keeps
        // counting only that, so «N из N» on the day screen is unchanged.
        //
        // ## «КАЖДЫЙ ДЕНЬ» IS A DAY, AND IT USED TO MEAN «EVERY SITTING»
        //
        // Every phrase the learner has already answered TODAY is skipped, whatever it owes. Without
        // that line the kit came back in the NEXT sitting of the same day, and every sitting after
        // it, with the same five cards — including the ones just answered correctly. Measured on the
        // owner's live day 1 (02.09, plan `01M1HZF4…`): the first sitting closed stage A for all
        // five, and the six sittings that followed dealt exactly those five again, one card each,
        // walking them up the ordinary rungs (word_bank → cloze → typing → listening) inside a
        // single evening. The day could not be finished by finishing it.
        //
        // Both halves of the canon survive the skip: the kit still comes back every morning (a new
        // local day has no answers in it yet), and it still climbs its accelerated ladder — A on the
        // day it is introduced, B after the first night — because that ladder is a fact about days
        // and not about sittings. What it stops doing is looping. A MISS is skipped too, on the same
        // line and on purpose: «промах по спасателю не добавляет посадок дню» (решение владельца) —
        // the phrase comes back tomorrow, where the miss is answered by the ladder rather than by
        // the learner sitting through it again.
        foreach ($this->rescueTerms($progress) as $termId => $rescue) {
            $standing = $rescue['standing'];
            $taken[$termId] = true;

            if ($standing->answeredToday) {
                continue;
            }

            if ($standing->nextMode !== null) {
                $specs = [
                    ...$specs,
                    ...$this->specsFor(
                        $termId,
                        $standing,
                        $rescue['day'],
                        'warmup',
                        $knobs,
                        $this->kindOf($progress, $termId),
                        PlanSessionTaskView::SECTION_WARMUP,
                    ),
                ];

                continue;
            }

            // THE KIT HAS WALKED ITS STAGES — and this is where «C с дня 3» happens (канон §5).
            //
            // A maintenance card every OTHER day, in the two modes the kit exists for: hear it said
            // to you, and say it with nothing on the screen. Not the ordinary selector's pick, which
            // is what «mode => null» used to mean here and what walked the five phrases up the
            // POOL's rungs — cloze, typing — inside a plan they belong to. Not every day either:
            // «два касания в неделю» would be too little and every morning too much for a phrase
            // whose stages are behind it, and the owner's rule is раз в 2 дня.
            //
            // «Every other» is anchored on the PHRASE's own yesterday rather than on a calendar
            // parity: a learner who misses Tuesday gets it on Wednesday instead of waiting out a
            // rhythm they never saw. Which of the two modes falls on this touch alternates with the
            // day, so two consecutive touches are never the same trainer.
            if ($standing->answeredYesterday) {
                continue;
            }
            $specs[] = ['term_id' => $termId, 'stage' => null,
                'mode' => PlanStageLadder::maintenanceModeFor(intdiv(self::dayNumber($progress->today), 2)),
                'ordinal' => 0,
                'of' => 0, 'day' => $rescue['day'], 'softened' => false, 'source' => 'warmup',
                'step' => null, 'section' => PlanSessionTaskView::SECTION_WARMUP];
        }

        // 0b. YESTERDAY'S DISOBEDIENT CARDS — the second half of разогрев v2 (канон §5).
        //
        // Up to five cards of the scene the learner answered WRONG yesterday, each dealt ONE light
        // touch — a recognition card, never the stage's full checklist. The distinction is the whole
        // point of the rule: a card that went wrong needs to be met again before the day starts, and
        // it does NOT need to be re-walked from the intro, which is what re-owing its stage would
        // do to a two-minute warm-up.
        //
        // YESTERDAY, and today's misses are deliberately out (DECISIONS п. 238). A card missed
        // twenty minutes ago is already coming back twice — at the end of this посадка (Ч-5) and in
        // tomorrow's warm-up — and putting it in today's as well would have one slip cost the
        // learner the same card three times in one evening.
        //
        // `answeredToday` skips it for the same reason it skips a rescue phrase: this is the DAY's
        // warm-up, not the sitting's, and the second sitting of an evening must not replay it.
        $misses = 0;
        foreach ($this->missedYesterday($progress) as $termId => $missed) {
            if ($misses >= self::WARMUP_MISS_CAP) {
                break;
            }
            if (isset($taken[$termId]) || $missed['standing']->answeredToday) {
                continue;
            }
            $taken[$termId] = true;
            $misses++;
            $specs[] = ['term_id' => $termId, 'stage' => null, 'mode' => ExerciseMode::MultipleChoice,
                'ordinal' => 0, 'of' => 0, 'day' => $missed['day'], 'softened' => false,
                'source' => 'warmup_miss', 'step' => null,
                'section' => PlanSessionTaskView::SECTION_WARMUP];
        }

        // 1. THE DAY ITSELF, in A2's order — words, connectors, replies, the interlocutor's line —
        // each card bringing its whole remaining stage-A checklist.
        //
        // First, and this is a change: the earlier days' revision used to be dealt ahead of it, to
        // «warm up on what you know before meeting what you do not». That reading held while those
        // cards were part of the DAY. They are now a section of their own with a name the learner
        // reads — «Повторение · из прошлых дней» — and a section announced after the material it
        // labels is not a section. The budget agrees: `array_slice` below cuts the tail, and stage A
        // has to close in ONE sitting or the day does not pass, so the day is what must not be cut.
        foreach ($this->orderedDayTerms($plan, $today) as $termId) {
            $standing = $today->standings[$termId] ?? null;
            if ($standing === null || isset($taken[$termId]) || $standing->nextMode === null) {
                continue;
            }
            $taken[$termId] = true;
            $specs = [
                ...$specs,
                ...$this->specsFor($termId, $standing, $dayIndex, 'new', $knobs, $this->kindOf($progress, $termId)),
            ];
        }

        // 2. THE SEAM: cards of ANOTHER day of this plan that the PLAN'S LADDER owes a card today —
        // stage A closed, a night passed, so they stand on B or C with something still open. B
        // first, because a word two stages from ready needs the sitting more than one that is nearly
        // there. A card that is both today's and owed is the day's, which is why this loop skips
        // what the first one took.
        //
        // `due_at` is not consulted, and that is С-2's fix: see the class docblock. The standings
        // are the same ones the day screen draws, so «эта карточка на ступени B» and «эту карточку
        // сегодня раздадут» are now one statement instead of two that agree by luck.
        //
        // NOT ON A DAY OPENED EARLY. The learner asked to look ahead; a revision of the days behind
        // them is not part of that question, and the canon's seam is «из прошлых дней» of the day
        // being studied.
        if ($isFocusDay) {
            foreach ([PlanStage::B, PlanStage::C] as $stage) {
                foreach ($progress->days as $index => $earlier) {
                    // EARLIER, and that is the caption's own word: «Повторение · из прошлых дней».
                    //
                    // A LATER day can stand on stage B too — the learner opened it ahead of its turn
                    // and walked it — and its cards are not stranded by being left out: bucket 1
                    // deals whatever the day being studied owes, at whatever stage it owes it, so
                    // they are dealt when that day comes round. Which is next, since the focus is
                    // the first day that has not passed.
                    if ($index >= $dayIndex) {
                        continue;
                    }
                    foreach ($earlier->termIds as $termId) {
                        $standing = $earlier->standings[$termId] ?? null;
                        if ($standing === null || isset($taken[$termId])) {
                            continue;
                        }
                        // OWED TODAY, on this stage. A card whose stage is closed and whose night
                        // has not passed has `nextMode === null` and is not in the sitting — which
                        // is the железное правило read from the other side.
                        if ($standing->stage !== $stage || $standing->nextMode === null) {
                            continue;
                        }
                        $taken[$termId] = true;
                        $specs = [
                            ...$specs,
                            ...$this->specsFor($termId, $standing, $index, 'plan_review', $knobs, $this->kindOf($progress, $termId)),
                        ];
                    }
                }
            }
        }

        // 3. ПРОГОН СЦЕНЫ — ступень C, последним в дне (наряд SCENE-RUN, Ч.2).
        //
        // Собирается только у сцены, каждый ход которой уже прошёл ступень B хотя бы одним верным
        // выбором ({@see PlanSceneRunGate}), и только один раз за день: прогон это событие, а
        // второй прогон той же сцены в тот же вечер измерял бы память о первом.
        foreach ($this->sceneRunSpecs($plan, $progress, $dayIndex) as $spec) {
            $specs[] = $spec;
        }

        // There is no third bucket. See the class docblock: the top-up is not filtered here, it is
        // not called, and there is nothing outside this plan for it to have read.

        // THE WHOLE DAY, and the minutes are a присест rather than a limit (Ч-6, {@see PlanSittings}).
        //
        // This used to be `array_slice($specs, 0, $budget)`: the learner's ten minutes CUT the day,
        // everything past the cut was never dealt, and the tail came back rebuilt from scratch at
        // whatever the ladder said next time. The day is one lesson; ten minutes is how long a
        // person sits down for. So the tail stays, `sittings` says where the breaks fall, and the
        // only ceiling left is {@see MAX_TASKS} — a sanity bound on a payload, not a teaching rule.
        // THE ORDER, and the conversations that order rests on — канон §10 ({@see ordered()}).
        // Applied before the budget bound, so «what gets cut» is the tail of the лesson rather than
        // whatever the buckets happened to build last.
        $specs = $this->ordered($specs, $progress, [], $dayIndex);
        $chains = $this->chainsFor($specs, $progress, $stages);
        // A second pass, now that the chains exist: the first one could not know where inside a
        // conversation a card stands, because the conversations are chosen by which cards are here.
        // Two cheap sorts over a few dozen specs, and the alternative is a chain built for every day
        // of the plan on every sitting.
        $specs = $chains === [] ? $specs : $this->ordered($specs, $progress, $chains, $dayIndex);

        return [
            $this->assembleTasks(
                $plan,
                array_slice($specs, 0, self::MAX_TASKS),
                $views,
                $this->contentFor($progress, $views, $plan),
                $knobs,
                $dayIndex,
                $today->collectionId,
                progress: $progress,
                stages: $stages,
            ),
            array_values($chains),
        ];
    }

    /**
     * ХОДЫ ПРОГОНА СЦЕНЫ ЭТОГО ДНЯ, или пусто — сцена до прогона ещё не дозрела (наряд SCENE-RUN).
     *
     * Один шаг на каждый ход `you` цепочки, в порядке цепочки, тренажёром «говорение»: реплики на
     * экране нет, есть подсказка на языке поддержки и микрофон. Тренажёр не новый и режим не новый —
     * новое только то, ЗАЧЕМ карточку раздали, и это говорит `section_code`.
     *
     * ## Три условия, и все три обязательны
     *
     * 1. У сцены есть цепочка и в ней есть свои ходы — прогонять иначе нечего.
     * 2. Каждый ход прошёл ступень B хотя бы одним верным выбором ({@see PlanSceneRunGate}).
     * 3. Сегодня эту сцену ещё не прогоняли. Прогон — событие, и второй за вечер мерил бы память о
     *    первом, а не умение.
     *
     * @return list<array<string, mixed>>
     */
    private function sceneRunSpecs(LearningPlan $plan, PlanProgressView $progress, int $dayIndex): array
    {
        // ЧТО УЖЕ ПРОГОНЯЛИ. Два разных вопроса из одного списка: «эту сцену сегодня уже гоняли»
        // (событие не повторяют в тот же вечер) и «эта сцена уже пройдена сама целиком» (ступень C
        // закрыта, возвращать её незачем).
        $ranToday = [];
        $closed = [];
        foreach ($this->sceneRuns->forPlan($plan->id()) as $run) {
            if ($run->dayIndex === $dayIndex) {
                $ranToday[$run->sceneIndex] = true;
            }
            if ($run->allSaid()) {
                $closed[$run->sceneIndex] = true;
            }
        }

        $indexes = array_keys($progress->days);
        sort($indexes);

        $specs = [];
        foreach ($indexes as $index) {
            // Сцены ВПЕРЕДИ дня, который изучают, не прогоняются: их ещё не проходили. Сцена самого
            // дня прогоняется — она могла дозреть на прошлой посадке.
            if ($index > $dayIndex || isset($ranToday[$index]) || isset($closed[$index])) {
                continue;
            }

            [$turns, $ready] = $this->sceneTurnsOf($progress->days[$index]);
            if ($turns === [] || ! $ready) {
                continue;
            }

            foreach ($turns as $termId) {
                $specs[] = [
                    'term_id' => $termId,
                    // Ступень C — то, чем прогон и является; она же решает, что реплики на экране
                    // нет ({@see PlanStage::speakingForm()} — `example_from_memory`).
                    'stage' => PlanStage::C,
                    'mode' => ExerciseMode::Speaking,
                    'ordinal' => 0,
                    'of' => 0,
                    'day' => $index,
                    'softened' => false,
                    'source' => 'scene_run',
                    'step' => null,
                    'section_code' => PlanSessionSections::SCENE_RUN,
                    'turn_level' => PlanTurnLevel::Say,
                    // ПРОГОН — ЧАСТЬ ДНЯ, а не шва, даже когда прогоняется вчерашняя сцена. Шов
                    // подписан «Повторение · из прошлых дней» и означает «вернулись к пройденному»;
                    // прогон это сегодняшний шаг лестницы, он входит в счётчик дня и в его итог.
                    'section' => PlanSessionTaskView::SECTION_DAY,
                ];
            }
        }

        return $specs;
    }

    /**
     * The learner's local day as a plain day count — the input to the maintenance alternation.
     *
     * A number that increases by one every calendar day and by nothing else, so two touches two days
     * apart are always two different trainers. `Y-m-d` parsed at midnight UTC: the DATE is already
     * the learner's own ({@see PlanProgress}), and re-applying a timezone to a date that has one
     * baked in is how an off-by-one enters.
     */
    private static function dayNumber(string $localDate): int
    {
        return intdiv((int) (new \DateTimeImmutable($localDate . ' 00:00:00', new \DateTimeZone('UTC')))->getTimestamp(), 86400);
    }

    /**
     * Every card of this plan the learner answered WRONG yesterday, day by day, in the order the
     * days were taught — the warm-up's second half.
     *
     * A `hear` card and a `word` are equally eligible: «непослушная» is a fact about the answer, not
     * about the shelf. The rescue kit falls out on its own, because the loop above has already
     * claimed those term ids.
     *
     * @return array<string, array{standing: PlanTermStanding, day: int}>
     */
    private function missedYesterday(PlanProgressView $progress): array
    {
        $out = [];
        foreach ($progress->days as $index => $day) {
            foreach ($day->termIds as $termId) {
                $standing = $day->standings[$termId] ?? null;
                if ($standing === null || ! $standing->missedYesterday) {
                    continue;
                }
                $out[$termId] ??= ['standing' => $standing, 'day' => $index];
            }
        }

        return $out;
    }

    /**
     * A word's remaining checklist for its current stage, as running-order slots.
     *
     * The WHOLE remainder, not one card: «переход к следующему режиму — сразу после успеха» means
     * the learner is meant to walk the stage in one sitting, and dealing one card per session would
     * make a five-step stage take five days — on a plan whose whole point is that it ends on a date.
     *
     * @return list<array<string, mixed>>
     */
    private function specsFor(
        string $termId,
        PlanTermStanding $standing,
        ?int $dayIndex,
        string $source,
        PlanKnobs $knobs,
        string $kind = PlanStageLadder::KIND_WORD,
        ?string $section = null,
    ): array {
        $specs = [];
        $seen = [];
        $total = count($standing->checklist);
        // Whether the assembler will deal the identity-graded recognition card decides the RUNG as
        // well as the card, and both read the same knob. See PlanStageLadder::ladderStepFor().
        $recognitionOptions = $knobs->optionsPolicy() === OptionsPolicy::Distant;

        foreach ($standing->checklist as $step) {
            $mode = ExerciseMode::tryFrom($step['mode']);
            if ($mode === null) {
                continue;
            }
            $seen[$step['mode']] = ($seen[$step['mode']] ?? 0) + 1;
            if ($step['done']) {
                continue;
            }

            $specs[] = [
                'term_id' => $termId,
                'stage' => $standing->stage,
                'mode' => $mode,
                'ordinal' => $step['ordinal'],
                'of' => $total,
                'day' => $dayIndex,
                'softened' => $standing->softened,
                'source' => $source,
                // Named by the caller when the card belongs to a seam of its own; otherwise the
                // day it came from decides, as it always has.
                'section' => $section,
                'step' => PlanStageLadder::ladderStepFor(
                    $standing->stage,
                    $mode,
                    $seen[$step['mode']],
                    $recognitionOptions,
                    // A LINE is graded against itself at every stage; a word's speaking card at B
                    // and C is graded against its example. The rung is what says which.
                    $kind,
                ),
            ];
        }

        return $specs;
    }

    /**
     * What the card at `$termId` DOES in its day, read off the progress the session was built from.
     *
     * `word` when the term never came from a plan day — every term written before plans existed has
     * no `kind`, and the word's ladder is the longest of the three, so nothing is skipped by
     * treating an unknown as one.
     */
    private function kindOf(PlanProgressView $progress, string $termId): string
    {
        foreach ($progress->days as $day) {
            $content = $day->content[$termId] ?? null;
            if ($content !== null && $content->kind !== null) {
                // The same derivation the checklist made ({@see PlanStandings}), from the same
                // object: the tier decides the ladder, and a session that asked a different
                // question would deal a step the checklist does not owe.
                return PlanStageLadder::ladderKindFor($content->kind, $content->tier, $content->shelf);
            }
        }

        return PlanStageLadder::KIND_WORD;
    }

    /**
     * THE FINAL DAY: every card the plan taught, once each, in the order it taught them.
     *
     * A run-through and nothing more. It is PRACTICE in the one sense the word has here — it
     * schedules nothing, closes no stage and moves no focus — because by the time the learner opens
     * it the teaching is done and what is left is the three minutes before the appointment. Grading
     * a rehearsal would also be the one way a plan could go BACKWARDS on its last morning.
     *
     * The material is read off the days that have already been written ({@see PlanProgress}), which
     * is the same content every other plan surface reads and therefore the same answer about what a
     * day holds — including the day-scoped example, which a second read here would have lost.
     *
     * ## WHAT A RUN-THROUGH IS MADE OF — and what it stopped being made of
     *
     * Recognition, the situation, and saying it out loud. Nothing typed, nothing dictated.
     *
     * The mode used to be left null, so the ORDINARY selector chose — and on the stand (С-9) it
     * chose, for the morning before the appointment: 16 dictations, 11 typing cards, 4 clozes and
     * not one speaking card. Half of it was the learner typing out the INTERLOCUTOR's lines from
     * audio, which is a keyboard test three minutes before a conversation. Канон §10/§12 asks for
     * «прогон всех сцен … вслух», so the mode is named here, per card, by what the card is:
     *
     *   «Тебе скажут» (понимаю)   the situation — hear it, choose what it meant
     *   «Ты ответишь» / «Ты спросишь»  their own situational card, and the tapped line said aloud
     *   слова, связки, спасатели  say it
     *
     * Each is a LIST rather than one mode, and the list is the fallback order: a situational card
     * whose shelf cannot furnish three same-shape options is refused by the assembler, and a
     * rehearsal that dropped the card would quietly leave a scene out of the run-through of it. The
     * strict path never falls back — there a refused card is dropped on purpose, because
     * substituting a trainer would close a checklist step nobody was asked.
     *
     * @return list<PlanSessionTaskView>
     */
    /**
     * @param  array<string, PlanTermStage>  $stages
     * @return array{0: list<PlanSessionTaskView>, 1: list<PlanDialogueView>}
     */
    private function rehearsalTasks(
        LearningPlan $plan,
        PlanProgressView $progress,
        PlanKnobs $knobs,
        array $stages,
    ): array {
        $budget = $this->taskBudget($plan);

        $indexes = array_keys($progress->days);
        sort($indexes);

        $views = $this->planViews($plan, $progress);
        $content = [];
        $specs = [];
        foreach ($indexes as $index) {
            $day = $progress->days[$index];
            $content += $day->content;

            [$turns] = $this->sceneTurnsOf($day);
            if ($turns === []) {
                continue;
            }
            foreach ($turns as $termId) {
                $specs[] = ['term_id' => $termId, 'stage' => PlanStage::C,
                    'mode' => ExerciseMode::Speaking,
                    'ordinal' => 0, 'of' => 0, 'day' => $index, 'softened' => false,
                    'source' => 'scene_run', 'step' => null,
                    'section_code' => PlanSessionSections::SCENE_RUN,
                    'turn_level' => PlanTurnLevel::Say];
            }
        }

        $specs = array_slice($specs, 0, $budget);
        $chains = $this->chainsFor($specs, $progress, $stages);

        return [
            $this->assembleTasks(
                $plan,
                $specs,
                $views,
                $content,
                $knobs,
                // The day being studied is the FINAL one, and no card belongs to it — every one of
                // them came from a teaching day, so every task is «Повторение» and the seam count is
                // zero. That is the honest reading: this sitting introduces nothing.
                $progress->focusDayIndex,
                null,
                isPractice: true,
                stages: $stages,
            ),
            array_values($chains),
        ];
    }

    /**
     * ХОДЫ `you` ЭТОЙ СЦЕНЫ и дозрела ли она до прогона — общий кусок обычного дня и финального.
     *
     * @return array{0: list<string>, 1: bool}
     */
    private function sceneTurnsOf(PlanDayProgressView $day): array
    {
        $turns = $this->sceneTurns->of($day);

        $standings = [];
        foreach ($turns as $termId) {
            $standing = $day->standings[$termId] ?? null;
            if ($standing === null) {
                return [$turns, false];
            }
            $standings[] = $standing;
        }

        return [$turns, PlanSceneRunGate::sceneIsReady($standings)];
    }

    // ── assembly ─────────────────────────────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $specs
     * @param  array<string, DueTermView>  $views
     * @param  array<string, TermContentView>  $content
     * @param  int  $dayIndex  the day BEING STUDIED — what tells the day's own cards from the seam
     * @param  string|null  $langCollectionId  the collection a card's PAIR is read through. Not the
     *         distractor pool and not the same question: the pool is the plan, the pair is the folder
     *         the card is being dealt out of.
     * @param  array<string, PlanTermStage>  $stages  what each pair has proved — {@see turnLevelFor()}
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
        array $stages = [],
    ): array {
        $enabled = $this->enabledModes->forUser($plan->userId());
        $matrix = $this->admission->matrixFor($plan->userId());

        // EVERY DAY'S SCENE, flattened once — the input a situational card's position is assembled
        // from ({@see SituationalPrompt}).
        //
        // Per DAY and not per sitting, and that is the whole subtlety: a card on stage B is a card
        // of an EARLIER day, dealt in the seam of this one. Its situation is its own scene's — the
        // вводка it was written under, and the role line of ITS day that asks the same thing. Built
        // out of today's scene instead, «Ты ответишь» from day 1 would be answering a question
        // nobody in that conversation asked.
        //
        // Null on a soft run and on the final day's rehearsal: neither walks a checklist, so
        // neither deals a situational card.
        $scenes = $this->scenesOf($progress);

        // THE OPTION POOL IS THE WHOLE PLAN, not today's folder. A word met on day 1 is the fairest
        // wrong answer this lesson has — same subject, same register, already seen — and it was
        // unreachable while the pool was one day's collection, which is what sent the reader off to
        // top up from the learner's other shelves in the first place.
        $poolIds = $this->planPoolIds($plan);

        // The far-option pool, exactly as the ordinary session builds it — the session's own words,
        // which is what makes a `far` distractor fair rather than arbitrary.
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
        /** @var array<string, int> $projected — счётчик выборов, спроецированный на эту посадку */
        $projected = [];
        foreach ($specs as $index => $spec) {
            /** @var string $termId */
            $termId = $spec['term_id'];
            $view = $views[$termId] ?? null;
            $termContent = $content[$termId] ?? null;
            if ($view === null || $termContent === null) {
                continue;
            }

            // THE TRAINERS THIS SLOT WILL ACCEPT, best first.
            //
            // One, on every path but the run-through: a checklist step IS a named trainer, so a slot
            // that fell back to another one would close a step the learner was never asked. The
            // final day's run-through closes nothing, and its list is the scene's own turns.
            /** @var list<ExerciseMode|null> $candidates */
            $candidates = isset($spec['modes']) && is_array($spec['modes']) && $spec['modes'] !== []
                ? $spec['modes']
                : [$spec['mode']];

            // ВАРИАНТЫ ТВОЕГО ХОДА — реплики плана, которые человек мог бы сказать ВМЕСТО этой
            // (наряд DAY-2-FIX, Ч.1.5). Считается по всему плану, своей сценой вперёд, и годится
            // только для say/ask — на такте понимания варианты это СМЫСЛЫ, а не реплики, и их
            // собирает {@see StudyCardAssembler::recognitionCard()} из того же дня.
            //
            // Живьём среди трёх ответов стояла другая реплика собеседника — человек выбирал, что
            // сказать, из того, что говорят ему. Второй дефект был тише и хуже: вариант с тем же
            // `skill_ref`, что у правильного ответа, отвечает на тот же вопрос и тоже верен.
            $answerPool = $this->answerPoolFor($termId, $termContent, $spec['day'], $scenes);

            // СТРОГОСТЬ ХОДА, если это ход. Считается ДО раздачи, потому что от неё зависит, что
            // вообще строится: выбор с вариантами или сборка с блоками.
            //
            // И считается ПО ХОДУ СБОРКИ ПОСАДКИ, а не один раз по хранимому счётчику. Ступень
            // проходят за одну посадку («переход к следующему тренажёру — сразу после успеха»), то
            // есть все три касания ступени B лежат в ОДНОМ пейлоаде; спросив хранимый счётчик
            // трижды, посадка раздала бы три выбора и закрыла ступень, ни разу не показав сборку —
            // то есть B+ не наступил бы никогда. Поэтому счётчик проецируется вперёд, как если бы
            // каждый выданный выбор был верным: ошибка возвращает карточку в хвост присеста, а
            // следующая сборка посадки берёт уже настоящий счётчик.
            $level = null;
            if (PlanAnswerOptions::isSpokenShelf($termContent->shelf)) {
                $streak = $projected[$termId] ?? self::streakOf($termId, $stages);
                $level = PlanDialogueLevel::forStreak($streak);
                $projected[$termId] = PlanDialogueLevel::after($streak, correct: true);
            }

            $card = null;
            foreach ($candidates as $mode) {
                $card = $this->assembler->assemble(
                    $plan->userId(), $view, $termContent, $poolIds, $enabled, $matrix,
                    isPractice: $isPractice,
                    cardIndex: $index,
                    slotStep: $spec['step'],
                    neighbours: $neighbours,
                    modeOverride: $mode,
                    supportLang: $langs->for($termId),
                    // The one knob the choice card already understood, and the level's own number.
                    optionCount: $knobs->mcOptions,
                    answerPoolIds: $answerPool,
                    turnLevel: $level,
                );
                if ($card !== null) {
                    break;
                }
            }
            // The assembler refused this card — the term's data could not build it after all, and on
            // the strict path there is nothing to fall back to on purpose (see above).
            if ($card === null) {
                continue;
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
            );

            $tasks[] = new PlanSessionTaskView(
                card: $card,
                stage: $stage?->value,
                ordinal: (int) $spec['ordinal'],
                ofSteps: (int) $spec['of'],
                fromDayIndex: $spec['day'] === null ? null : (int) $spec['day'],
                softened: (bool) $spec['softened'],
                source: (string) $spec['source'],
                // THE SEAM, named on the server. Every card of the sitting now comes out of a day of
                // THIS plan, so «which day» is the whole question: today's is the day, an earlier
                // one is «Повторение». It used to read «`day` is null», which was the top-up — and
                // with the top-up gone that test would have made the seam permanently empty and the
                // day's own count permanently equal to the sitting.
                section: is_string($spec['section'] ?? null)
                    ? $spec['section']
                    : ((int) $spec['day'] === $dayIndex
                        ? PlanSessionTaskView::SECTION_DAY
                        : PlanSessionTaskView::SECTION_REVIEW),
                // NOBODY ELSE'S SHELF TO NAME. `origin` said «из плана: Отпуск в Италии» over a card
                // the top-up had brought in; the seam is now this plan's own earlier days, and
                // `from_day_index` above already says which. The field stays on the wire — it is
                // what the client would need the day a foreign card is ever dealt again — and it
                // stays null while there is nothing true to put in it.
                origin: null,
                speakingForm: $dealt === ExerciseMode::Speaking
                    ? $stage?->speakingForm($termContent->kind ?? PlanStageLadder::KIND_WORD)
                    : null,
                // WHERE THE GAP IS CUT. The day's own frame when the card has one — «I worked on
                // ___» — and the example otherwise, which is what every card outside a plan has
                // always used. A line's example is the turn AROUND it, so cutting a gap there would
                // blank a word the card never taught.
                clozeSource: $dealt === ExerciseMode::Cloze
                    ? ($termContent->frame ?? $termContent->example)
                    : null,
                knobsApplied: PlanKnobSupport::appliedTo($dealt),
                knobsIgnored: PlanKnobSupport::ignoredBy($dealt),
                // WHOSE LINE. Carried on every task rather than only on a role one, so the client
                // never has to read «null» as «the learner's» — a term that is not a plan line has
                // no speaker at all, and that is a third answer (Д-8).
                speaker: $termContent->speaker,
                // WHAT THIS CARD IS, so the summary can count «4 слова · 2 связки · 8 фраз» instead
                // of counting words in the text and calling a connector a phrase (Д-5).
                kind: $termContent->kind,
                // WHICH SHELF, and therefore which caption the client draws over the seam —
                // «Тебе скажут», «Ты ответишь», «Ты спросишь», «Слова и связки», «Разогрев». The
                // server names it because the shelf is a fact about the day and the client has two
                // languages to say it in.
                shelf: $termContent->shelf,
                // `speak` or `understand`. The client does not decide anything with it; it is here
                // so a card the server will only ever ask for RECOGNITION cannot be drawn as one
                // the learner is expected to produce (Д-8).
                tier: $termContent->tier,
                // THE POSITION THE CARD PUTS THE LEARNER IN — on the situational trainers and
                // nowhere else. Assembled on the server because it is a fact about the DAY (the
                // scene's вводка, its role lines, its abilities) and this is the only place that
                // holds all three at once.
                situation: $situation?->toArray(),
                // …and whether the learner says the tapped line out loud afterwards. Not graded and
                // not uploaded ({@see ExerciseMode::speaksAfterChoice()}); it is on the wire so the
                // client does not have to know which of the three modes is which.
                speaksAfterChoice: $dealt->speaksAfterChoice(),
                // WHAT THE LEARNER IS DOING — «Разогрев», «Слова и связки», «Знакомство с
                // репликами», «Диалог сцены» — as a code the client localises. Stamped by
                // {@see ordered()} on every strict path; the run-through has no scene to be a part
                // of and says so.
                sectionCode: is_string($spec['section_code'] ?? null)
                    ? $spec['section_code']
                    : PlanSessionSections::REHEARSAL,
                // СТРОГОСТЬ ХОДА, и только у хода: выбор или сборка на двух говорящих полках,
                // ничего — у реплики собеседника, слова, связки и разогрева. Считается по паре, а
                // не по карточке, потому что доказывает выбор ПАРА (план, термин), а карточка
                // сегодняшней раздачи — только её последнее доказательство.
                turnLevel: match (true) {
                    // Ведро назвало уровень само — прогон сцены: `say`, микрофон, реплики нет.
                    ($spec['turn_level'] ?? null) instanceof PlanTurnLevel => $spec['turn_level']->value,
                    $dealt->speaksAfterChoice() => $level?->value,
                    default => null,
                },
            );
        }

        return $tasks;
    }

    // ── the pieces ───────────────────────────────────────────────────────────────────────────

    /**
     * HOW MANY CARDS FIT IN ONE ПРИСЕСТ — the learner's chosen minutes, priced in cards.
     *
     * Priced at this learner's own measured seconds-per-card — the same figure and the same default
     * the home screen uses, because a plan that says «20 минут» on one screen and deals twice that on
     * another is lying about the one number the learner chose. Note this is not the scheduler's
     * `capacity`, which counts TERMS: a word met today brings its whole stage-A checklist, so nine
     * terms is far more than nine cards.
     *
     * NOT capped at {@see MAX_TASKS}, and that stopped being a detail with Ч-6. While the budget CUT
     * the session, the cap was the same bound seen twice and cost nothing. Now it is the length of a
     * присест, and a cap would collapse 20 minutes and 40 minutes onto the same number for any
     * learner whose cards are quick — the plan would break a forty-minute sitting where a
     * twenty-minute one breaks, which is the opposite of what the learner asked for. The payload's
     * own ceiling is applied where it belongs, to the tasks.
     */
    private function taskBudget(LearningPlan $plan): int
    {
        $seconds = $this->home->averageCardSeconds($plan->userId(), self::LATENCY_SAMPLE) ?? self::DEFAULT_CARD_SECONDS;

        return max(1, intdiv($plan->minutesPerDay() * 60, max(1, $seconds)));
    }

    /**
     * EVERY TERM THIS PLAN STANDS ON, all days — the pool a plan card's wrong answers come from.
     *
     * Read off the day COLLECTIONS rather than off `$progress`, because the soft session has no
     * progress view and the answer must be the same for both: a plan's options are the plan's, and
     * «which sitting is this» has nothing to do with it.
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

    /** The day's terms in A2's order — the pieces, the connectors, the replies, the interlocutor's. */
    /** @return list<string> */
    private function orderedDayTerms(LearningPlan $plan, PlanDayProgressView $day): array
    {
        $cards = [];
        foreach ($day->termIds as $termId) {
            $content = $day->content[$termId] ?? null;
            if ($content === null) {
                continue;
            }
            $cards[] = new PlanDayCard(
                termId: $termId,
                shelf: $content->shelf,
                // What the card DOES in its day, written when the day was generated. It used to be
                // guessed from `type` («anything that is not one word is a reply»), which was right
                // until v0.2 put connectors in a day: «deal with» is two words and a substitution.
                // A term written before plans existed carries no kind at all, and the old guess is
                // still the only thing to go on for it.
                kind: $content->kind ?? ($content->type === 'word'
                    ? PlanStageLadder::KIND_WORD
                    : PlanStageLadder::KIND_LINE),
                isRoleLine: $content->speaker === self::SPEAKER_ROLE,
                difficultyScore: null,
            );
        }

        return $this->order->order($cards, $plan->level());
    }

    /** `terms.speaker` for a line the INTERLOCUTOR says — {@see PlanStandings::PRODUCTION_MODES}. */
    private const SPEAKER_ROLE = 'role';

    /** The shelf the server's own five phrases stand on — {@see PlanShelf::Rescue}. */
    private const SHELF_RESCUE = 'rescue';

    /**
     * THE RESCUE KIT OF THIS PLAN, with where each phrase stands — the warm-up's whole input.
     *
     * Found by SHELF and not by a list of texts: the five phrases are written into day 1 as
     * ordinary cards ({@see \App\Modules\Generation\Application\Command\GeneratePlanDayHandler}), and
     * a session that matched them by comparing strings against a config would deal a different set
     * the day somebody fixed a comma in the language pack.
     *
     * They live on day 1, so their standings are day 1's — which is also what makes «ускоренная
     * лестница» true without a second ladder: A on the day they are introduced, B in the session
     * after the first night, C after the second, exactly as the plan's own ladder walks anything.
     *
     * @return array<string, array{standing: PlanTermStanding, day: int}>
     */
    private function rescueTerms(PlanProgressView $progress): array
    {
        $out = [];
        foreach ($progress->days as $index => $day) {
            foreach ($day->termIds as $termId) {
                $content = $day->content[$termId] ?? null;
                $standing = $day->standings[$termId] ?? null;
                if ($content === null || $standing === null || $content->shelf !== self::SHELF_RESCUE) {
                    continue;
                }
                $out[$termId] ??= ['standing' => $standing, 'day' => $index];
            }
        }

        return $out;
    }

    /**
     * THE PROGRESS ROW OF EVERY CARD THE PLAN STANDS ON, all days at once — one read.
     *
     * The whole plan and not «today plus whatever is due», because since С-2 the sitting is chosen
     * by the LADDER: a card of day 1 owed its stage-B trainer, or a rescue phrase owed its daily
     * touch, has no `due_at` claim to be found by, and a spec whose term has no view here is
     * silently dropped in {@see assembleTasks()}. That drop is what emptied the warm-up and the seam
     * on the stand while both said, on the very same payload, that they were owed.
     *
     * A card with no progress row at all is {@see DueTermView::outOfPool()} — the same answer
     * free practice fills its gaps with. It happens for a card of a day the learner has not opened
     * yet, which is exactly the material an early-opened day deals.
     *
     * NUMBERS ARE NOT HERE, and that is not this method's doing: {@see PlanProgress} leaves them out
     * of `termIds` because no session deals them yet (канон §6). A shelf nobody can be dealt cannot
     * be dealt by accident from here either.
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

        // Terms from outside the plan: no day, so no day's example — the general one, which is what
        // «иначе общие» means.
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
                // A soft run IS practice, in the one sense the word has here: it never schedules and
                // never advances anything. The strict session is study, and its answers are ordinary
                // reviews the repetition planner reads.
                isPractice: ! $strict,
                composition: $composition,
                startedAt: $now,
                collectionId: $day->collectionId() === null ? null : CollectionId::fromString($day->collectionId()->value),
            ));
        });
    }
}
