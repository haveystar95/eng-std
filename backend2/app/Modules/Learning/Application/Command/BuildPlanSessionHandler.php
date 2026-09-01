<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Collections\Application\Port\CollectionPairReader;
use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Learning\Application\Dto\PlanSessionView;
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\HomePlanReader;
use App\Modules\Learning\Application\Port\PlanDayCollectionTitles;
use App\Modules\Learning\Application\Port\ModeAdmissionReader;
use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
use App\Modules\Learning\Application\Query\GetDueTerms;
use App\Modules\Learning\Application\Query\GetDueTermsHandler;
use App\Modules\Learning\Application\Query\GetPracticeTerms;
use App\Modules\Learning\Application\Query\GetPracticeTermsHandler;
use App\Modules\Learning\Application\Service\CardLanguageResolver;
use App\Modules\Learning\Application\Service\PlanDayPassing;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Application\Service\StudyCardAssembler;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\Service\PlanDayOrder;
use App\Modules\Learning\Domain\Service\PlanGenerationPolicy;
use App\Modules\Learning\Domain\Service\PlanKnobSupport;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanDayCard;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
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
 * ## The four buckets, in the order they are dealt
 *
 *   1. **plan reviews** — words of this plan that the repetition planner says are due, and that
 *      stand on stage B or C. B before C, because a word two stages from ready needs the sitting
 *      more than one that is nearly there. First in the session: warm up on what you know before
 *      meeting what you do not.
 *   2. **new** — this day's words that have not closed stage A, in the order A2 computed
 *      ({@see PlanDayOrder}) — words before replies below `conversational`, replies before words
 *      above it. Each brings its whole remaining stage-A checklist, because stage A has to close in
 *      ONE sitting or the day does not pass.
 *   3. **other due** — everything else the planner has due today **in this plan's language pair**,
 *      if the day's minutes have room left. A plan does not suspend the rest of the learner's
 *      vocabulary; it also does not teach French inside an English lesson, which is what the
 *      unfiltered version did on a live day ({@see inPlanPair()}).
 *
 * The order of the three is the CONTRACT, not an implementation detail: everything belonging to the
 * plan comes first and the top-up follows, so a client can draw the seam between «сегодняшний день»
 * and «повторение» without guessing. `day_task_count` on the payload says where it falls, and every
 * task carries its own {@see PlanSessionTaskView::$section}.
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
 * Soft, deliberately. The learner may look at day 3 on day 1 — the material exists, and refusing to
 * show it would be pretending it does not. But it is a PRACTICE session: it schedules nothing,
 * closes no stage and does not move the focus, because a day walked through before its turn has not
 * been done, it has been read.
 */
final readonly class BuildPlanSessionHandler
{
    /** Seconds a card costs when this learner has no measured figure yet — the home screen's. */
    private const DEFAULT_CARD_SECONDS = 8;

    /** How many recent answers the per-learner card-seconds figure is measured over. */
    private const LATENCY_SAMPLE = 50;

    /** A session is a sitting, not a marathon, whatever the minutes work out to. */
    private const MAX_TASKS = 120;

    /** Bound on the due read: the same order of magnitude as the ordinary session's. */
    private const DUE_CAP = 100;

    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
        private PlanModeSettingsReader $planSettings,
        private GetDueTermsHandler $dueTerms,
        private GetPracticeTermsHandler $practiceTerms,
        private UserCollectionTermsReader $collectionTerms,
        private TermContentReader $content,
        private CardLanguageResolver $languages,
        private CollectionPairReader $collectionsByTerm,
        private PlanDayCollectionTitles $planTitles,
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
        private PlanDayOrder $order = new PlanDayOrder(),
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

        $strict = $dayIndex === $progress->focusDayIndex && $day->kind() === PlanDayKind::Intro;
        $knobs = $this->planSettings->knobsFor($plan->level());

        $tasks = $strict
            ? $this->strictTasks($plan, $progress, $dayIndex, $knobs)
            : $this->softTasks($plan, $day, $knobs);

        $sessionId = $command->sessionId ?? StudySessionId::generate();
        $this->persist($sessionId, $plan, $day, $tasks, $strict);

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
        );
    }

    // ── the strict session ───────────────────────────────────────────────────────────────────

    /** @return list<PlanSessionTaskView> */
    private function strictTasks(
        LearningPlan $plan,
        PlanProgressView $progress,
        int $dayIndex,
        PlanKnobs $knobs,
    ): array {
        $today = $progress->days[$dayIndex] ?? null;
        if ($today === null) {
            return [];
        }

        $budget = $this->taskBudget($plan);
        $standings = $progress->allStandings();
        $dayOf = $this->dayOfTerm($progress);

        // Everything the planner says is due, with NO new terms: what is new here is the day's own
        // material, and the daily quota is about the ordinary pool, not about a plan the learner
        // has committed to.
        $due = $this->inPlanPair($plan, ($this->dueTerms)(new GetDueTerms(
            userId: $plan->userId(),
            now: $this->clock->now(),
            sessionSize: self::DUE_CAP,
            newTermsRemaining: 0,
        )), $standings);

        /** @var array<string, DueTermView> $views */
        $views = [];
        foreach ($due as $view) {
            $views[$view->termId->value] = $view;
        }
        // The day's own words need a view too, and they are not due — they have never been seen.
        foreach ($this->dayViews($plan, $today) as $termId => $view) {
            $views[$termId] ??= $view;
        }

        $specs = [];
        $taken = [];

        // 1. Plan words that are due and standing on B or C — B first.
        foreach ([PlanStage::B, PlanStage::C] as $stage) {
            foreach ($due as $view) {
                $termId = $view->termId->value;
                $standing = $standings[$termId] ?? null;
                if ($standing === null || $standing->stage !== $stage || isset($taken[$termId])) {
                    continue;
                }
                $taken[$termId] = true;
                $specs = [
                ...$specs,
                ...$this->specsFor($termId, $standing, $dayOf[$termId] ?? null, 'plan_review', $knobs, $this->kindOf($progress, $termId)),
            ];
            }
        }

        // 2. The day's own words, in A2's order, each bringing its whole remaining stage-A checklist.
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

        // 3. Whatever else is due, if the minutes have room.
        foreach ($due as $view) {
            $termId = $view->termId->value;
            if (isset($taken[$termId]) || isset($standings[$termId])) {
                continue;
            }
            $taken[$termId] = true;
            $specs[] = ['term_id' => $termId, 'stage' => null, 'mode' => null, 'ordinal' => 0,
                'of' => 0, 'day' => null, 'softened' => false, 'source' => 'other_review', 'step' => null];
        }

        return $this->assembleTasks(
            $plan,
            array_slice($specs, 0, $budget),
            $views,
            $this->contentFor($progress, $views, $plan),
            $knobs,
            $today->collectionId,
        );
    }

    /**
     * THE DUE LIST, NARROWED TO THIS PLAN'S LANGUAGE PAIR.
     *
     * Bucket 3 exists so a plan does not suspend the rest of the learner's vocabulary — but «the
     * rest of the vocabulary» meant, literally, everything the planner had due, in any language the
     * learner has ever saved a word in. A live day of an `ru→en` plan dealt two FRENCH cards and
     * five lines out of a different plan: seven of its sixty-three tasks were nothing to do with
     * the day, and one of them asked «выбери французский эквивалент» inside an English lesson.
     *
     * They were not even unlucky. Both French pairs stood at `acquisition: learning` with no
     * `due_at`, and the due query orders `due_at ASC NULLS FIRST` — so they were at the HEAD of the
     * queue and would have led every session until answered.
     *
     * A term of THIS PLAN is kept whatever its pair resolves to: the plan's own material is dealt by
     * the plan (`$standings` is every term of every day of it), and a day's word that also sits in
     * an older folder of another pair must not disappear from its own day because
     * {@see CollectionPairReader::supportLangByTerm()} picked that older folder.
     *
     * Everything else has to match BOTH halves: the term's own language is what the learner would
     * have to say, and the support language is what the card would ask in.
     *
     * ## AND A LINE OF ANOTHER PLAN IS NEVER TOPPED UP
     *
     * The pair filter alone left the worse half of the incident standing. «Hi, I'm Alex, and I work
     * as a backend developer.» is English, in the learner's own `ru→en` pair, and their own word —
     * so it passed — and it arrived in a HOLIDAY plan as a task to be studied, mid-lesson, out of an
     * interview plan they had abandoned. A `line` is a turn in ONE conversation; away from that
     * conversation it is a sentence with nowhere to be said. Words and connectors travel — that is
     * what vocabulary is — and lines are revised inside their own plan or not at all.
     *
     * A term with NO `kind` travels too: that is ordinary vocabulary, written before plans existed
     * or saved by hand, and «a plan does not suspend the rest of the learner's vocabulary» is the
     * whole reason this bucket exists. The rule excludes lines, not everything that is not a
     * plan's word.
     *
     * @param  list<DueTermView>  $due
     * @param  array<string, PlanTermStanding>  $standings  every term this plan stands on
     * @return list<DueTermView>
     */
    private function inPlanPair(LearningPlan $plan, array $due, array $standings): array
    {
        $foreign = [];
        foreach ($due as $view) {
            if (! isset($standings[$view->termId->value])) {
                $foreign[] = $view->termId->value;
            }
        }
        if ($foreign === []) {
            return $due;
        }

        $ids = array_map(static fn (string $id): TermId => TermId::fromString($id), $foreign);
        $langs = $this->languages->forTerms($plan->userId(), $ids);
        $content = $this->content->byIds($ids, $langs);

        $target = self::langKey($plan->targetLang()->value);
        $support = self::langKey($plan->supportLang()->value);

        $keep = [];
        foreach ($foreign as $termId) {
            $view = $content[$termId] ?? null;
            $keep[$termId] = $view !== null
                && $view->kind !== PlanStageLadder::KIND_LINE
                && self::langKey($view->lang) === $target
                && self::langKey($langs->for($termId)) === $support;
        }

        return array_values(array_filter(
            $due,
            static fn (DueTermView $v): bool => $keep[$v->termId->value] ?? true,
        ));
    }

    /**
     * «Отпуск в Италии» — the shelf each of these terms came off, named the way a person names it.
     *
     * A card of the top-up is a word the learner met somewhere else. Dropped into the middle of a
     * plan's lesson with nothing said about it, it reads as part of today — and on the day that
     * started all this, «Привет, я Алекс, и я работаю бэкенд-разработчиком» turned up in a lesson
     * about a holiday and the learner did not recognise their own word.
     *
     * A collection that IS a plan's day answers as the PLAN: «день 1 плана Отпуск в Италии» is a
     * folder the learner never made and whose name they would not recognise either.
     *
     * @param  list<string>  $termIds
     * @return array<string, array{kind: string, title: string}>
     */
    private function originsFor(LearningPlan $plan, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $collections = $this->collectionsByTerm->collectionByTerm($plan->userId(), $termIds);
        $planTitles = $this->planTitles->titlesByDayCollection(array_values(array_unique(array_map(
            static fn (array $c): string => $c['id'],
            $collections,
        ))));

        $out = [];
        foreach ($collections as $termId => $collection) {
            $planTitle = $planTitles[$collection['id']] ?? null;
            $out[$termId] = $planTitle !== null
                ? ['kind' => PlanSessionTaskView::ORIGIN_PLAN, 'title' => $planTitle]
                : ['kind' => PlanSessionTaskView::ORIGIN_COLLECTION, 'title' => $collection['title']];
        }

        return $out;
    }

    /** Language codes as they compare: «EN» and «en» are one language, «en-GB» is not «en». */
    private static function langKey(string $lang): string
    {
        return mb_strtolower(trim($lang));
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
                return $content->kind;
            }
        }

        return PlanStageLadder::KIND_WORD;
    }

    // ── the soft session ─────────────────────────────────────────────────────────────────────

    /**
     * A day opened out of turn: its collection, shuffled, one card each, no stages.
     *
     * @return list<PlanSessionTaskView>
     */
    private function softTasks(LearningPlan $plan, PlanDay $day, PlanKnobs $knobs): array
    {
        $collectionId = $day->collectionId()?->value;
        if ($collectionId === null) {
            return [];
        }

        $views = ($this->practiceTerms)(new GetPracticeTerms(
            userId: $plan->userId(),
            sessionSize: $this->taskBudget($plan),
            collectionId: $collectionId,
        ));

        $byTerm = [];
        $specs = [];
        foreach ($views as $view) {
            $byTerm[$view->termId->value] = $view;
            $specs[] = ['term_id' => $view->termId->value, 'stage' => null, 'mode' => null,
                'ordinal' => 0, 'of' => 0, 'day' => $day->dayIndex(), 'softened' => false,
                'source' => 'soft', 'step' => null];
        }

        $ids = array_map(static fn (DueTermView $v): TermId => $v->termId, $views);
        $content = $this->content->byIds(
            $ids,
            $this->languages->forTerms($plan->userId(), $ids, $collectionId),
            scopeCollectionId: $collectionId,
        );

        return $this->assembleTasks($plan, $specs, $byTerm, $content, $knobs, $collectionId, isPractice: true);
    }

    // ── assembly ─────────────────────────────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $specs
     * @param  array<string, DueTermView>  $views
     * @param  array<string, TermContentView>  $content
     * @return list<PlanSessionTaskView>
     */
    private function assembleTasks(
        LearningPlan $plan,
        array $specs,
        array $views,
        array $content,
        PlanKnobs $knobs,
        ?string $poolCollectionId,
        bool $isPractice = false,
    ): array {
        $enabled = $this->enabledModes->forUser($plan->userId());
        $matrix = $this->admission->matrixFor($plan->userId());

        $poolIds = $poolCollectionId !== null
            ? $this->collectionTerms->termIdsForCollection($plan->userId(), $poolCollectionId, self::DUE_CAP)
            : array_keys($content);

        // The far-option pool, exactly as the ordinary session builds it — the session's own words,
        // which is what makes a `far` distractor fair rather than arbitrary.
        $neighbours = [];
        $langs = $this->languages->forTerms(
            $plan->userId(),
            array_map(static fn (string $id): TermId => TermId::fromString($id), array_keys($content)),
            $poolCollectionId,
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

        // WHERE EACH REVIEW CARD CAME FROM. Resolved once for the whole session and only for the
        // top-up: the day's own cards are the day and need no label.
        $origins = $this->originsFor($plan, array_values(array_unique(array_map(
            static fn (array $spec): string => (string) $spec['term_id'],
            array_filter($specs, static fn (array $spec): bool => $spec['day'] === null),
        ))));

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
            $mode = $spec['mode'];
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
            );
            // The assembler refused this card — the term's data could not build it after all. The
            // TASK is dropped rather than replaced: substituting another trainer would close a
            // checklist step the learner was never asked.
            if ($card === null) {
                continue;
            }

            /** @var PlanStage|null $stage */
            $stage = $spec['stage'];
            $dealt = ExerciseMode::from($card->exerciseMode);

            $tasks[] = new PlanSessionTaskView(
                card: $card,
                stage: $stage?->value,
                ordinal: (int) $spec['ordinal'],
                ofSteps: (int) $spec['of'],
                fromDayIndex: $spec['day'] === null ? null : (int) $spec['day'],
                softened: (bool) $spec['softened'],
                source: (string) $spec['source'],
                // THE SEAM, named on the server. A task belongs to the day exactly when it came out
                // of a day of this plan — which is «`day` is not null», the rule the client was
                // supposed to apply and did not.
                section: $spec['day'] === null
                    ? PlanSessionTaskView::SECTION_REVIEW
                    : PlanSessionTaskView::SECTION_DAY,
                origin: $spec['day'] === null ? ($origins[$termId] ?? null) : null,
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
            );
        }

        return $tasks;
    }

    // ── the pieces ───────────────────────────────────────────────────────────────────────────

    /**
     * How many cards fit in the day's minutes.
     *
     * Priced in CARDS at this learner's own measured seconds-per-card — the same figure and the same
     * default the home screen uses, because a day that says «20 минут» on one screen and deals twice
     * that on another is the plan lying about the one number the learner chose. Note this is not the
     * scheduler's `capacity`, which counts TERMS: a word met today brings its whole stage-A
     * checklist, so nine terms is far more than nine cards.
     */
    private function taskBudget(LearningPlan $plan): int
    {
        $seconds = $this->home->averageCardSeconds($plan->userId(), self::LATENCY_SAMPLE) ?? self::DEFAULT_CARD_SECONDS;

        return max(1, min(self::MAX_TASKS, intdiv($plan->minutesPerDay() * 60, max(1, $seconds))));
    }

    /** The day's terms in A2's order — words before replies, or the other way, by level. */
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
                // `is_line` is a fact about the TERM, written when the day was generated. It is read
                // through the term's shape here because that is what the content reader carries; a
                // reply is a phrase and a substitution word is a word.
                // What the card DOES in its day, written when the day was generated. It used to be
                // guessed from `type` («anything that is not one word is a reply»), which was right
                // until v0.2 put connectors in a day: «deal with» is two words and a substitution.
                isLine: ($content->kind ?? '') === PlanStageLadder::KIND_LINE
                    || ($content->kind === null && $content->type !== 'word'),
                difficultyScore: null,
            );
        }

        return $this->order->order($cards, $plan->level());
    }

    /** @return array<string, DueTermView> */
    private function dayViews(LearningPlan $plan, PlanDayProgressView $day): array
    {
        if ($day->collectionId === null) {
            return [];
        }

        $out = [];
        foreach (($this->practiceTerms)(new GetPracticeTerms(
            userId: $plan->userId(),
            sessionSize: count($day->termIds),
            collectionId: $day->collectionId,
        )) as $view) {
            $out[$view->termId->value] = $view;
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

    /** @return array<string, int> term id => the day of this plan it was introduced on */
    private function dayOfTerm(PlanProgressView $progress): array
    {
        $out = [];
        foreach ($progress->days as $index => $day) {
            foreach ($day->termIds as $termId) {
                $out[$termId] ??= $index;
            }
        }

        return $out;
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
