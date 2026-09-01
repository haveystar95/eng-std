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
use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
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
 * ## A SITTING IS MADE OF THIS PLAN'S CARDS AND OF NOTHING ELSE
 *
 * Two buckets, in the order they are dealt:
 *
 *   1. **new** — this day's words that have not closed stage A, in the order A2 computed
 *      ({@see PlanDayOrder}) — words before replies below `conversational`, replies before words
 *      above it. Each brings its whole remaining stage-A checklist, because stage A has to close in
 *      ONE sitting or the day does not pass.
 *   2. **plan reviews** — words of this plan, introduced on an EARLIER day, that the repetition
 *      planner says are due and that stand on stage B or C. B before C, because a word two stages
 *      from ready needs the sitting more than one that is nearly there. This is the seam the learner
 *      reads as «Повторение · из прошлых дней».
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
 * nobody had thought of. So the read itself is scoped ({@see DueTermsReader::selectableForPlan()}):
 * the session cannot deal a word this plan did not enrol, because it never reads one.
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
        /**
         * The plan's OWN due words — its seam, and the only due list a plan session reads. Not
         * {@see GetDueTermsHandler}: that query answers «what does the trainer deal next out of the
         * POOL», and the pool is by definition everything no running plan is standing on.
         */
        private DueTermsReader $dueInPlan,
        private GetPracticeTermsHandler $practiceTerms,
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

        // THIS PLAN'S OWN WORDS THAT THE PLANNER HAS MADE DUE AGAIN — and nothing else in the world.
        //
        // It used to be the learner's whole due list ({@see GetDueTerms}), which is how bucket 3 was
        // fed and how a word out of an abandoned holiday plan turned up in a lesson about renting a
        // flat. The list is now the plan's own by construction rather than by filter: there is no
        // predicate here that could be forgotten or widened, because nothing outside the plan is
        // ever read.
        $due = $this->dueInPlan->selectableForPlan(
            $plan->userId(),
            $plan->id()->value,
            $this->clock->now(),
            self::DUE_CAP,
        );

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

        // 1. THE DAY ITSELF, in A2's order, each word bringing its whole remaining stage-A checklist.
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

        // 2. THE SEAM: words of an EARLIER day of this plan that the planner has made due again and
        // that stand on stage B or C — B first, because a word two stages from ready needs the
        // sitting more than one that is nearly there. A word that is both today's and due is the
        // day's, which is why this loop skips what the first one took.
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

        // There is no third bucket. See the class docblock: the top-up is not filtered here, it is
        // not called, and there is nothing outside this plan for it to have read.

        return $this->assembleTasks(
            $plan,
            array_slice($specs, 0, $budget),
            $views,
            $this->contentFor($progress, $views, $plan),
            $knobs,
            $dayIndex,
            $today->collectionId,
        );
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

        return $this->assembleTasks(
            $plan, $specs, $byTerm, $content, $knobs, $day->dayIndex(), $collectionId, isPractice: true,
        );
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
    ): array {
        $enabled = $this->enabledModes->forUser($plan->userId());
        $matrix = $this->admission->matrixFor($plan->userId());

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
                // THE SEAM, named on the server. Every card of the sitting now comes out of a day of
                // THIS plan, so «which day» is the whole question: today's is the day, an earlier
                // one is «Повторение». It used to read «`day` is null», which was the top-up — and
                // with the top-up gone that test would have made the seam permanently empty and the
                // day's own count permanently equal to the sitting.
                section: (int) $spec['day'] === $dayIndex
                    ? PlanSessionTaskView::SECTION_DAY
                    : PlanSessionTaskView::SECTION_REVIEW,
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
