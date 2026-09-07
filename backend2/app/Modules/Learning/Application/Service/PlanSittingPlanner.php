<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Application\Dto\PlanDialogueTurnView;
use App\Modules\Learning\Application\Dto\PlanDialogueView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Repository\PlanSceneRunRepository;
use App\Modules\Learning\Domain\Service\PlanDayOrder;
use App\Modules\Learning\Domain\Service\PlanDialogueChain;
use App\Modules\Learning\Domain\Service\PlanSceneRunGate;
use App\Modules\Learning\Domain\Service\PlanSessionSections;
use App\Modules\Learning\Domain\Service\PlanSittings;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use App\Modules\Learning\Domain\ValueObject\PlanDayCard;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Learning\Domain\ValueObject\PlanTurnLevel;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;

/**
 * ЧТО РАЗДАЁТ ДЕНЬ — план посадки до того, как из него сделали карточки (наряд DAY-FIX-2, Ч.3).
 *
 * The bucket rules of a strict plan sitting used to live inside
 * {@see \App\Modules\Learning\Application\Command\BuildPlanSessionHandler}, and only that handler
 * could say how many cards a day had left — by building the whole session, which persists one.
 * Three screens then said three different things about the same day (Plan tab, day screen, sitting
 * header), because each counted on its own. So the PLANNING is a service of its own: it reads the
 * progress the plan screen already computes and answers «which specs, in which order, with which
 * conversations» without touching the assembler, the audio index or the database. The session
 * builder turns those specs into cards; the day-state census
 * ({@see PlanDayStateCensus}) turns them into «идёт · около 6 минут». One answer, two readers.
 *
 * ## A SITTING IS MADE OF THIS PLAN'S CARDS AND OF NOTHING ELSE
 *
 *   0. **the warm-up** — the rescue kit, once a day, and up to five of yesterday's misses (канон §5);
 *   1. **the day itself** — this day's own cards, in канон §11's order, each bringing what its
 *      stage owes today: the whole remainder for a word, ONE show for a line (Ч.2.4);
 *   2. **the seam** — cards of EARLIER days that the plan's own ladder owes a card today, on
 *      stage B or C, as ONE touch by assembly (Ч.2.3), words capped by the words-section budget;
 *   3. **the прогон** — ступень C of every scene that has matured, once a day (SCENE-RUN).
 *
 * ## THE BUDGET (Ч.2, `config/learning.php → plan.budget`; наряд DAY-FIX-3, Ч.4)
 *
 * A day is TWO sittings: «Материал» — the warm-up, the words, the introductions and their
 * exercises — of at most `material_max_cards`, and «Разговор» — the dialogue, the seam's lines and
 * the прогон — of at most `conversation_max_cards`. What can overrun is trimmed from the tail
 * ({@see trimmed()}). The words section («Слова и связки») holds at most `words_section_cards`:
 * today's own words first, the seam's words while they fit.
 *
 * ## «Когда» is not this planner's business
 *
 * Nothing here reads or writes `due_at`, an interval or an ease. The plan says WHICH card; the
 * repetition planner says WHEN a word comes back, and it learns that from the review log.
 */
final readonly class PlanSittingPlanner
{
    /** How many of yesterday's misses the warm-up carries (канон §5, разогрев v2). */
    public const WARMUP_MISS_CAP = 5;

    /** The ceiling on ONE PAYLOAD — a sanity bound, not a teaching rule. */
    public const MAX_TASKS = 120;

    public function __construct(
        private PlanSceneRunRepository $sceneRuns,
        private PlanSceneTurns $sceneTurns,
        /**
         * @var array{material_max_cards: int, conversation_max_cards: int, words_section_cards: int, rescue_warmup_cards: int, card_seconds: int, word_choice_options: int}
         */
        private array $budget,
        private PlanDayOrder $order = new PlanDayOrder(),
        private PlanDialogueChain $dialogues = new PlanDialogueChain(),
    ) {}

    /**
     * THE LAYOUT OF ONE STRICT SITTING — specs in running order, and the conversations they rest on.
     *
     * @param  array<string, \App\Modules\Learning\Domain\ValueObject\PlanTermStage>  $stages
     */
    public function plan(LearningPlan $plan, PlanProgressView $progress, int $dayIndex, PlanKnobs $knobs, array $stages): PlanSittingLayout
    {
        $today = $progress->days[$dayIndex] ?? null;
        if ($today === null) {
            return new PlanSittingLayout([], [], $this->budget['card_seconds']);
        }

        // WHETHER THIS IS THE DAY THE PLAN IS ON. It decides one thing and one thing only — whether
        // the sitting carries a seam — and everything else about the sitting is identical.
        $isFocusDay = $dayIndex === $progress->focusDayIndex;

        $specs = [];
        $taken = [];

        // 0. THE WARM-UP — the rescue kit, ONCE A DAY, before anything else (канон §5), at most
        // `rescue_warmup_cards` of it (Ч.2.5). Every phrase already answered TODAY is skipped: this
        // is the DAY's warm-up, not the sitting's.
        $rescueCards = 0;
        foreach ($this->rescueTerms($progress) as $termId => $rescue) {
            $standing = $rescue['standing'];
            $taken[$termId] = true;

            if ($standing->answeredToday || $rescueCards >= $this->budget['rescue_warmup_cards']) {
                continue;
            }

            if ($standing->nextMode !== null) {
                $owed = $this->specsFor($termId, $standing, $rescue['day'], 'warmup', $knobs,
                    $this->kindOf($progress, $termId), PlanSessionTaskView::SECTION_WARMUP);
                $rescueCards += count($owed);
                $specs = [...$specs, ...$owed];

                continue;
            }

            // THE KIT HAS WALKED ITS STAGES — maintenance every OTHER day, alternating between the
            // two modes the kit exists for: assemble it, and say it with nothing on the screen.
            // A stage closed TODAY and waiting for its night is not «walked»: the phrase owes
            // nothing more today (PlanWarmupOnceADayTest — the kit is dealt once a day).
            if ($standing->waitingForNight || $standing->answeredYesterday) {
                continue;
            }
            $rescueCards++;
            $specs[] = ['term_id' => $termId, 'stage' => null,
                'mode' => PlanStageLadder::maintenanceModeFor(intdiv(self::dayNumber($progress->today), 2)),
                'ordinal' => 0,
                'of' => 0, 'day' => $rescue['day'], 'softened' => false, 'source' => 'warmup',
                'step' => null, 'section' => PlanSessionTaskView::SECTION_WARMUP];
        }

        // 0b. YESTERDAY'S DISOBEDIENT CARDS — one light touch each (канон §5, разогрев v2).
        $misses = 0;
        foreach ($this->missedYesterday($progress) as $termId => $missed) {
            if ($misses >= self::WARMUP_MISS_CAP) {
                break;
            }
            if (isset($taken[$termId]) || $missed['standing']->answeredToday) {
                continue;
            }
            // A LINE PAST ITS INTRODUCTION IS NOT TOUCHED HERE (наряд DAY-FIX-3, стенд 07.09). Its
            // stage shows ONCE a day (Ч.2.4), and a recognition card would BE that show: the line
            // is «taken», the dialogue turn the ladder owes it today is never dealt, and a day whose
            // line was missed yesterday cannot close today — the live day 2 stood at «почти» with
            // three replies answered by a translation choice and nothing left to deal. The ladder's
            // own card is the light touch: a missed reply comes back in today's conversation.
            if ($missed['standing']->stage !== PlanStage::A
                && PlanStageLadder::oneShowPerDay($this->kindOf($progress, $termId))) {
                continue;
            }
            $taken[$termId] = true;
            $misses++;
            $specs[] = ['term_id' => $termId, 'stage' => null, 'mode' => ExerciseMode::MultipleChoice,
                'ordinal' => 0, 'of' => 0, 'day' => $missed['day'], 'softened' => false,
                'source' => 'warmup_miss', 'step' => null,
                'section' => PlanSessionTaskView::SECTION_WARMUP];
        }

        // 1. THE DAY ITSELF, in канон order, each card bringing what its stage owes today.
        $wordCards = 0;
        foreach ($this->orderedDayTerms($plan, $today) as $termId) {
            $standing = $today->standings[$termId] ?? null;
            if ($standing === null || isset($taken[$termId]) || $standing->nextMode === null) {
                continue;
            }
            $taken[$termId] = true;
            $kind = $this->kindOf($progress, $termId);
            $owed = $this->specsFor($termId, $standing, $dayIndex, 'new', $knobs, $kind);
            if (self::isWordKind($kind)) {
                $wordCards += count($owed);
                // WHAT THE BUDGET MAY GIVE UP LAST: a topical word stands in no line of the scene
                // (P2 v0.8), so it is the one card of the day the conversation can do without.
                $topical = isset($today->content[$termId]) && $today->content[$termId]->topical;
                $owed = array_map(static fn (array $s): array => $s + ['topical' => $topical], $owed);
            }
            // THE SCENE IS SPOKEN THE DAY IT IS MET (решение владельца 05.09, DECISIONS п. 266): a
            // line's stage B opens in the SAME sitting as its A, so the conversation follows the
            // introduction rather than waiting for a night. The standing cannot say so yet — the
            // log holds no fact about a stage nobody has reached — so the planner deals the first
            // step of B on the ladder's own word.
            if ($owed !== [] && $standing->stage === PlanStage::A && ! $standing->answeredToday
                && PlanStageLadder::opensBSameDay($kind)) {
                $owed = [...$owed, ...$this->sameDayStageB($termId, $standing, $dayIndex, $knobs, $kind)];
            }
            $specs = [...$specs, ...$owed];
        }

        // 2. THE SEAM: cards of EARLIER days the ladder owes today, OLDEST DAY FIRST and within a
        // day B before C, one touch each. Oldest first because the words section is capped
        // (Ч.2.2) and the cap is what a seam word competes for: a word met three days ago and
        // owed its «say it» rung has waited longer than yesterday's word owed its first choice —
        // and the rung the plan walks toward is C. Not on a day opened early: the learner asked to
        // look ahead, not to revise.
        if ($isFocusDay) {
            $earlierDays = array_keys($progress->days);
            sort($earlierDays);
            foreach ($earlierDays as $index) {
                $earlier = $progress->days[$index];
                foreach ([PlanStage::B, PlanStage::C] as $stage) {
                    if ($index >= $dayIndex) {
                        continue;
                    }
                    foreach ($earlier->termIds as $termId) {
                        $standing = $earlier->standings[$termId] ?? null;
                        if ($standing === null || isset($taken[$termId])) {
                            continue;
                        }
                        if ($standing->stage !== $stage || $standing->nextMode === null) {
                            continue;
                        }
                        $kind = $this->kindOf($progress, $termId);
                        // THE WORDS SECTION IS CAPPED (Ч.2.2): a seam word past the cap waits for
                        // tomorrow, and it is not «taken», so nothing about it is lost.
                        if (self::isWordKind($kind) && $wordCards >= $this->budget['words_section_cards']) {
                            continue;
                        }
                        $taken[$termId] = true;
                        $owed = $this->specsFor($termId, $standing, $index, 'plan_review', $knobs, $kind);
                        if (self::isWordKind($kind)) {
                            $wordCards += count($owed);
                        }
                        // What the seam card IS — the budget trims words before scene lines.
                        $owed = array_map(
                            static fn (array $s): array => $s + ['seam_word' => self::isWordKind($kind)],
                            $owed,
                        );
                        $specs = [...$specs, ...$owed];
                    }
                }
            }
        }

        // 3. ПРОГОН СЦЕНЫ — ступень C, последним в дне (наряд SCENE-RUN, Ч.2).
        foreach ($this->sceneRunSpecs($plan, $progress, $dayIndex) as $spec) {
            $specs[] = $spec;
        }

        // THE ORDER, and the conversations that order rests on — канон §10 ({@see ordered()}).
        $specs = $this->ordered($specs, $progress, [], $dayIndex);
        $chains = $this->chainsFor($specs, $progress, $dayIndex);
        $specs = $chains === [] ? $specs : $this->ordered($specs, $progress, $chains, $dayIndex);

        // THE LEVEL OF EVERY TURN, decided here so the census, the chain and the card agree.
        $specs = $this->withTurnLevels($specs, $progress, $dayIndex);

        // THE BUDGET: «Материал» and «Разговор», each under its own ceiling.
        $specs = $this->trimmed($specs);

        return new PlanSittingLayout(
            array_slice($specs, 0, self::MAX_TASKS),
            array_values($chains),
            $this->budget['card_seconds'],
        );
    }

    /**
     * THE FINAL DAY: the прогон of EVERY scene, in order, matured or not (наряд SCENE-RUN, Ч.2.8).
     *
     * A run-through and nothing more — it schedules nothing, closes no stage and moves no focus.
     * Every scene is run, including the ones the ladder never reached: tomorrow is the counter, and
     * the scene the learner did not get to is exactly where it will be frightening. The chain of
     * such a scene carries `run_ready = false`, and the summary marks it rather than pretending.
     */
    public function rehearsal(LearningPlan $plan, PlanProgressView $progress): PlanSittingLayout
    {
        $indexes = array_keys($progress->days);
        sort($indexes);

        $specs = [];
        foreach ($indexes as $index) {
            [$turns] = $this->sceneTurnsOf($progress->days[$index]);
            foreach ($turns as $termId) {
                $specs[] = ['term_id' => $termId, 'stage' => PlanStage::C,
                    'mode' => ExerciseMode::Speaking,
                    'ordinal' => 0, 'of' => 0, 'day' => $index, 'softened' => false,
                    'source' => 'scene_run', 'step' => null,
                    'section_code' => PlanSessionSections::SCENE_RUN,
                    'turn_level' => PlanTurnLevel::Say];
            }
        }

        $specs = array_slice($specs, 0, self::MAX_TASKS);

        return new PlanSittingLayout(
            $specs,
            array_values($this->chainsFor($specs, $progress, $progress->focusDayIndex)),
            $this->budget['card_seconds'],
        );
    }

    /**
     * ДВА ПРИСЕСТА, ДВА ПОТОЛКА (наряд DAY-FIX-3, Ч.4.1): «Материал» ≤ `material_max_cards`,
     * «Разговор» ≤ `conversation_max_cards`.
     *
     * The material gives way from its tail: the seam first, then the warm-up's misses, then the
     * warm-up's own rescue cards (the kit is back tomorrow), and only then the day's own TOPICAL
     * words, whole — the one card of the day the scene's conversation does not need (P2 v0.8). The
     * conversation gives way the same way: the seam's lines first (a line of a day behind
     * comes back tomorrow), then the прогон of the OLDEST scene, whole — a scene run is one act and
     * cannot be dealt half. Today's own dialogue is never trimmed: the day cannot pass without it.
     *
     * @param  list<array<string, mixed>>  $specs
     * @return list<array<string, mixed>>
     */
    private function trimmed(array $specs): array
    {
        $count = static fn (array $list, string $kind): int => count(array_filter(
            $list,
            static fn (array $s): bool => PlanSittings::kindOf(self::sectionKeyOfSpec($s)) === $kind,
        ));

        // THE DAY'S OWN MATERIAL GIVES WAY LAST, and a rescue card of the warm-up before it: the
        // kit comes back every morning anyway, a topical word of the scene does not (живой стенд
        // 07.09 — day 1 and day 2 stood at 46 against 45, and the card that went was the scene's).
        $material = max(1, $this->budget['material_max_cards']);
        foreach (['plan_review', 'warmup_miss', 'warmup', 'topical'] as $source) {
            while ($count($specs, PlanSittings::MATERIAL) > $material) {
                // THE SEAM GIVES WAY NEWEST DAY FIRST: a card of the day before yesterday has waited
                // longer than one of yesterday, and its rung (C for a word) is what the plan is
                // walking toward. Within a day, WORDS before the scene's lines — the seam is there
                // for the conversation to come back by assembly (Ч.2.3), a word's recognition can
                // wait a morning — and among equals the last dealt goes first.
                $drop = null;
                $dropKey = null;
                foreach ($specs as $i => $spec) {
                    if (PlanSittings::kindOf(self::sectionKeyOfSpec($spec)) !== PlanSittings::MATERIAL) {
                        continue;
                    }
                    $matches = $source === 'topical'
                        ? (($spec['topical'] ?? false) === true)
                        : ($spec['source'] ?? null) === $source;
                    if (! $matches) {
                        continue;
                    }
                    $key = [(int) ($spec['day'] ?? 0), ($spec['seam_word'] ?? false) ? 1 : 0, $i];
                    if ($drop === null || $key >= $dropKey) {
                        $drop = $i;
                        $dropKey = $key;
                    }
                }
                if ($drop === null) {
                    break;
                }
                // A TOPICAL WORD GOES WHOLE — its introduction with its exercise. Dropping the
                // exercise alone left the intro in the sitting and the word «познакомился» with
                // its tiles owed tomorrow, and the day could not pass on the evening it was walked
                // (живой прогон 07.09: «health insurance», 46 cards against 45).
                $termId = $specs[$drop]['term_id'];
                $specs = array_values(array_filter(
                    $specs,
                    static fn (array $s, int $i): bool => $i !== $drop
                        && ! ($source === 'topical' && $s['term_id'] === $termId && ($s['source'] ?? null) === 'new'),
                    ARRAY_FILTER_USE_BOTH,
                ));
            }
        }

        $conversation = max(1, $this->budget['conversation_max_cards']);
        while ($count($specs, PlanSittings::CONVERSATION) > $conversation) {
            $drop = null;
            $dropKey = null;
            foreach ($specs as $i => $spec) {
                if (PlanSittings::kindOf(self::sectionKeyOfSpec($spec)) !== PlanSittings::CONVERSATION
                    || ($spec['source'] ?? null) !== 'plan_review') {
                    continue;
                }
                $key = [(int) ($spec['day'] ?? 0), $i];
                if ($drop === null || $key >= $dropKey) {
                    $drop = $i;
                    $dropKey = $key;
                }
            }
            if ($drop !== null) {
                array_splice($specs, $drop, 1);

                continue;
            }
            // No seam left — the прогон of the oldest scene goes, whole.
            $oldest = null;
            foreach ($specs as $spec) {
                if (($spec['section_code'] ?? null) === PlanSessionSections::SCENE_RUN) {
                    $oldest = $oldest === null ? (int) $spec['day'] : min($oldest, (int) $spec['day']);
                }
            }
            if ($oldest === null) {
                break;
            }
            $specs = array_values(array_filter(
                $specs,
                static fn (array $s): bool => ! (($s['section_code'] ?? null) === PlanSessionSections::SCENE_RUN && (int) $s['day'] === $oldest),
            ));
        }

        return $specs;
    }

    /**
     * СТРОГОСТЬ КАЖДОГО ХОДА — на спеке, one rule for the card and the chain (Ч.1.4, Ч.2.3).
     *
     * @param  list<array<string, mixed>>  $specs
     * @return list<array<string, mixed>>
     */
    private function withTurnLevels(array $specs, PlanProgressView $progress, int $dayIndex): array
    {
        foreach ($specs as $i => $spec) {
            if (($spec['turn_level'] ?? null) instanceof PlanTurnLevel) {
                continue;
            }
            /** @var string $termId */
            $termId = $spec['term_id'];
            $shelf = self::shelfOf($progress, $termId);
            if (! in_array($shelf, [PlanDialogueChain::SHELF_SAY, PlanDialogueChain::SHELF_ASK], true)) {
                continue;
            }
            // THE EXERCISE OF STAGE A IS THE ASSEMBLY (наряд DAY-FIX-3, Ч.3.1): a line just met is
            // built from its blocks before it is ever chosen among others. Choice is the
            // conversation's first touch, and the conversation is stage B.
            $specs[$i]['turn_level'] = ($spec['stage'] ?? null) === PlanStage::A
                ? PlanTurnLevel::Assemble
                : PlanTurnLevel::forTurn(
                    $shelf,
                    (int) $spec['ordinal'],
                    inSeam: $spec['day'] !== null && (int) $spec['day'] !== $dayIndex,
                );
        }

        return $specs;
    }

    /**
     * The section key a task will be cut by — {@see sectionKeyOf()}.
     *
     * @param  array<string, mixed>  $spec
     */
    public static function sectionKeyOfSpec(array $spec): string
    {
        return ($spec['section'] ?? null) === PlanSessionTaskView::SECTION_WARMUP
            ? PlanSessionSections::WARMUP
            : (string) ($spec['section_code'] ?? PlanSessionSections::DAY) . '#' . ((int) ($spec['day'] ?? 0));
    }

    /**
     * ПРИСЕСТЫ, as the layout would cut them — «Материал», then «Разговор».
     *
     * @param  list<array<string, mixed>>  $specs
     * @return list<int>
     */
    public static function sittingsOf(array $specs): array
    {
        return PlanSittings::split(array_map(self::sectionKeyOfSpec(...), $specs));
    }

    /**
     * How many cards of the layout fall into the sitting of this kind.
     *
     * @param  list<array<string, mixed>>  $specs
     */
    public static function cardsOfKind(array $specs, string $kind): int
    {
        return count(array_filter(
            $specs,
            static fn (array $s): bool => PlanSittings::kindOf(self::sectionKeyOfSpec($s)) === $kind,
        ));
    }

    // ── the pieces ───────────────────────────────────────────────────────────────────────────

    private static function isWordKind(string $kind): bool
    {
        return $kind === PlanStageLadder::KIND_WORD || $kind === PlanStageLadder::KIND_CHUNK;
    }

    /**
     * A word's remaining checklist for its current stage, as running-order slots — the WHOLE
     * remainder for a word or a rescue phrase, ONE step for a line of the scene (Ч.2.4: «реплика
     * сцены в один день — не больше одного показа на ступень»).
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
        $recognitionOptions = $knobs->optionsPolicy() === OptionsPolicy::Distant;
        $oneShow = PlanStageLadder::oneShowPerDay($kind);

        // ONE SHOW PER STAGE PER DAY (DAY-FIX-2, Ч.2.4): a line answered today — right or wrong —
        // owes nothing more today on the stage it answered. Its next step is tomorrow's; a miss is
        // re-dealt by the sitting's own queue, not by a second server card. An intro still open is
        // not an answer, though: a reply chosen before its intro was acknowledged (the sitting was
        // left mid-way) still owes the intro, or stage A would never close.
        if ($oneShow && $standing->answeredToday && $standing->stage !== PlanStage::A) {
            return [];
        }

        foreach ($standing->checklist as $step) {
            $mode = ExerciseMode::tryFrom($step['mode']);
            if ($mode === null) {
                continue;
            }
            $seen[$step['mode']] = ($seen[$step['mode']] ?? 0) + 1;
            if ($step['done']) {
                continue;
            }
            // …AND STAGE A IS DEALT WHOLE (наряд DAY-FIX-3, Ч.3): the introduction and the exercise
            // that closes it are one evening's work for every kind of card. «One show» is a rule
            // about the conversation's stage, not about meeting the line.
            if ($oneShow && $specs !== [] && $standing->stage !== PlanStage::A) {
                break;
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
                'section' => $section,
                'step' => PlanStageLadder::ladderStepFor(
                    $standing->stage,
                    $mode,
                    $seen[$step['mode']],
                    $recognitionOptions,
                    $kind,
                ),
                // A WORD'S TRANSLATION IS CHOSEN OUT OF FOUR on the day it is met (Ч.3.1) — the
                // level's knob sets the choice everywhere else. Null means «the knob».
                'options' => $standing->stage === PlanStage::A && $mode === ExerciseMode::MultipleChoice
                    && PlanStageLadder::isWordLike($kind)
                    ? $this->budget['word_choice_options']
                    : null,
            ];
        }

        return $specs;
    }

    /**
     * THE FIRST STEP OF STAGE B for a line met today — dealt on the ladder's word, before the log
     * knows the stage ({@see PlanStageLadder::opensBSameDay()}). The same shape {@see specsFor()}
     * gives a step the standing already names, so the card, the chain and the census read it alike.
     *
     * @return list<array<string, mixed>>
     */
    private function sameDayStageB(string $termId, PlanTermStanding $standing, ?int $dayIndex, PlanKnobs $knobs, string $kind): array
    {
        $first = PlanStageLadder::firstStepOf(PlanStage::B, $kind);
        if ($first === null) {
            return [];
        }
        [$mode, $of] = $first;

        return [[
            'term_id' => $termId,
            'stage' => PlanStage::B,
            'mode' => $mode,
            'ordinal' => 1,
            'of' => $of,
            'day' => $dayIndex,
            'softened' => $standing->softened,
            'source' => 'new',
            'section' => null,
            'step' => PlanStageLadder::ladderStepFor(
                PlanStage::B,
                $mode,
                1,
                $knobs->optionsPolicy() === OptionsPolicy::Distant,
                $kind,
            ),
        ]];
    }

    /**
     * ХОДЫ ПРОГОНА СЦЕНЫ ЭТОГО ДНЯ, или пусто — сцена до прогона ещё не дозрела (наряд SCENE-RUN).
     *
     * @return list<array<string, mixed>>
     */
    private function sceneRunSpecs(LearningPlan $plan, PlanProgressView $progress, int $dayIndex): array
    {
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
                    'section' => PlanSessionTaskView::SECTION_DAY,
                ];
            }
        }

        return $specs;
    }

    /**
     * ХОДЫ `you` ЭТОЙ СЦЕНЫ и дозрела ли она до прогона.
     *
     * @return array{0: list<string>, 1: bool}
     */
    public function sceneTurnsOf(PlanDayProgressView $day): array
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

    /**
     * THE RUNNING ORDER OF A SITTING — канон §10, applied to specs that already exist.
     *
     * @param  list<array<string, mixed>>  $specs
     * @param  array<int, PlanDialogueView>  $chains  day index => that scene's conversation
     * @return list<array<string, mixed>>  the same specs, ordered, each stamped with `section_code`
     */
    private function ordered(array $specs, PlanProgressView $progress, array $chains, int $dayIndex): array
    {
        $atTurn = [];
        foreach ($chains as $day => $chain) {
            foreach ($chain->turns as $position => $turn) {
                $atTurn[$day . '#' . $turn->termId] ??= $position;
            }
        }

        $warmup = [];
        $rest = [];
        foreach ($specs as $position => $spec) {
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
                    // «МАТЕРИАЛ», ПОТОМ «РАЗГОВОР» (наряд DAY-FIX-3, Ч.4): everything the learner
                    // meets and exercises, then everything they say — today's dialogue, the seam's
                    // lines, the прогон. Two sittings, cut here and nowhere else
                    // ({@see PlanSittings::split()}).
                    PlanSittings::kindOf($spec['section_code'] . '#' . $day) === PlanSittings::CONVERSATION ? 1 : 0,
                    // THE DAY, THEN THE SEAM, within each sitting.
                    $day === $dayIndex ? 0 : 1,
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
     * THE CONVERSATIONS THIS SITTING PLAYS — one per scene that has a dialogue or a run in it.
     *
     * @param  list<array<string, mixed>>  $specs  already stamped with `section_code`
     * @return array<int, PlanDialogueView>  day index => that scene's conversation
     */
    private function chainsFor(array $specs, PlanProgressView $progress, int $dayIndex): array
    {
        $wanted = [];
        foreach ($specs as $spec) {
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

            $turns = [];
            foreach ($this->dialogues->for($day->dialogue, self::candidatesOf($day)) as $move) {
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
                    level: $move->isRole()
                        ? null
                        : self::turnLevelFor($move->termId, $content->shelf, $progress->days, $index !== $dayIndex)->value,
                    pairKind: $move->pair,
                );
            }

            if ($turns !== []) {
                $out[$index] = new PlanDialogueView(
                    dayIndex: $index,
                    sceneTitle: $day->sceneTitle,
                    sceneIntro: $day->sceneIntro,
                    turns: $turns,
                    runReady: $this->sceneTurnsOf($day)[1],
                );
            }
        }

        return $out;
    }

    /**
     * СТРОГОСТЬ ХОДА ЭТОЙ РЕПЛИКИ ДЛЯ ЛЕНТЫ — по стойке пары, а не по задаче.
     *
     * @param  array<int, PlanDayProgressView>  $days
     */
    private static function turnLevelFor(string $termId, ?string $shelf, array $days, bool $inSeam): PlanTurnLevel
    {
        foreach ($days as $day) {
            $standing = $day->standings[$termId] ?? null;
            if ($standing === null || $standing->stage !== PlanStage::B) {
                continue;
            }

            $done = 0;
            foreach ($standing->checklist as $step) {
                if ($step['done']) {
                    $done++;
                }
            }

            return PlanTurnLevel::forTurn($shelf, $done + 1, $inSeam);
        }

        return PlanTurnLevel::forTurn($shelf, 1, $inSeam);
    }

    /** @return list<SituationalCandidate> */
    public static function candidatesOf(PlanDayProgressView $day): array
    {
        $cards = [];
        foreach ($day->content as $termId => $view) {
            $cards[] = new SituationalCandidate($termId, $view->shelf, $view->skillRef, $view->text);
        }

        return $cards;
    }

    /** @param array<string, mixed> $spec */
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
    public static function shelfOf(PlanProgressView $progress, string $termId): ?string
    {
        foreach ($progress->days as $day) {
            $content = $day->content[$termId] ?? null;
            if ($content !== null) {
                return $content->shelf;
            }
        }

        return null;
    }

    /** What the card at `$termId` DOES in its day — the ladder kind. */
    public function kindOf(PlanProgressView $progress, string $termId): string
    {
        foreach ($progress->days as $day) {
            $content = $day->content[$termId] ?? null;
            if ($content !== null && $content->kind !== null) {
                return PlanStageLadder::ladderKindFor($content->kind, $content->tier, $content->shelf);
            }
        }

        return PlanStageLadder::KIND_WORD;
    }

    /** The learner's local day as a plain day count — the input to the maintenance alternation. */
    private static function dayNumber(string $localDate): int
    {
        return intdiv((int) (new \DateTimeImmutable($localDate . ' 00:00:00', new \DateTimeZone('UTC')))->getTimestamp(), 86400);
    }

    /** @return array<string, array{standing: PlanTermStanding, day: int}> */
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

    /** The day's terms in канон order — the pieces, the connectors, the replies, the interlocutor's. */
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
                kind: $content->kind ?? ($content->type === 'word'
                    ? PlanStageLadder::KIND_WORD
                    : PlanStageLadder::KIND_LINE),
                isRoleLine: $content->speaker === self::SPEAKER_ROLE,
                difficultyScore: null,
            );
        }

        return $this->order->order($cards, $plan->level());
    }

    private const SPEAKER_ROLE = 'role';

    public const SHELF_RESCUE = 'rescue';

    /** @return array<string, array{standing: PlanTermStanding, day: int}> */
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
}
