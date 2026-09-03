<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Service\RoleLineModes;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

/**
 * THE ONE COMPUTATION both the plan session and the plan screen run on.
 *
 * Two callers need the same answer — «where is every word of this plan, and which day is the learner
 * on» — and they must not be allowed to compute it differently: a session built against one focus
 * and a screen drawn against another is the kind of disagreement that looks like a client bug for a
 * week. So the arithmetic lives here, the command path uses it to deal cards and to write
 * `learning_plan_days.status`, and the query path uses it to render.
 *
 * ## The cost, stated
 *
 * One collection read (two since PLAN-FIX-4: the ids, and the date each of them joined — the plan
 * ladder's cutoff, see {@see PlanStandings}), one content read and one review-log read per
 * introduction day — up to fourteen of each
 * ({@see \App\Modules\Learning\Domain\Service\PlanScheduler::MAX_INTRO_DAYS}). The
 * content read is per DAY rather than for the whole plan on purpose: a term's example is chosen
 * through the collection it is being shown in, and «сначала пример этого дня, иначе общий» is only
 * expressible one scope at a time.
 *
 * Plus, since PLAN-FIX-5, one option read PER CARD THAT THE DAY ITSELF CANNOT FURNISH A CHOICE FOR
 * ({@see PlanStandings::optionsAvailable()}) — a card the day answers costs nothing. Measured on
 * the owner's three-day plan: 154 ms for the whole plan, eight such cards on day 1, ~6 ms each. A
 * fourteen-day plan whose every card is short would pay about a second, and if one ever does the
 * fix is a batched count in the reader rather than a return to counting the day.
 */
final readonly class PlanProgress
{
    /** A day's collection cannot be larger than a day, but the read still needs a bound. */
    private const TERMS_PER_DAY_CAP = 200;

    public function __construct(
        private UserCollectionTermsReader $collectionTerms,
        private TermContentReader $content,
        private CardLanguageResolver $languages,
        private PlanStandings $standings,
        private LearnerProfileReader $profile,
        private Clock $clock,
    ) {}

    /** @param list<PlanDay> $days */
    public function forPlan(LearningPlan $plan, array $days): PlanProgressView
    {
        $tz = $this->profile->timezoneFor($plan->userId());
        $now = $this->clock->now()->setTimezone($tz);
        $today = $now->format('Y-m-d');
        // THE LEARNER'S OWN YESTERDAY, computed once here because this is the only place that holds
        // their timezone. `modify` on a zoned date, not «minus 86400 seconds»: a day is 23 or 25
        // hours twice a year, and the warm-up would then read the wrong day's misses on exactly the
        // mornings a person is most likely to be somewhere unfamiliar.
        $yesterday = $now->modify('-1 day')->format('Y-m-d');

        $progress = [];
        foreach ($days as $day) {
            if ($day->kind() !== PlanDayKind::Intro) {
                continue;
            }
            $progress[$day->dayIndex()] = $this->dayProgress($plan, $day, $today, $tz, $yesterday);
        }

        return new PlanProgressView(
            days: $progress,
            focusDayIndex: $this->focusOf($days, $progress),
            today: $today,
        );
    }

    private function dayProgress(LearningPlan $plan, PlanDay $day, string $today, \DateTimeZone $tz, string $yesterday): PlanDayProgressView
    {
        $collectionId = $day->collectionId()?->value;
        if ($collectionId === null) {
            // The day has not been written yet. Not passed, no words, and the focus stops here —
            // which is right: there is nothing to study and nothing to move past.
            return new PlanDayProgressView($day->dayIndex(), null, [], [], [], passed: false);
        }

        $termIds = $this->collectionTerms->termIdsForCollection(
            $plan->userId(),
            $collectionId,
            self::TERMS_PER_DAY_CAP,
        );
        if ($termIds === []) {
            return new PlanDayProgressView($day->dayIndex(), $collectionId, [], [], [], passed: false);
        }

        $ids = array_map(static fn (string $id): TermId => TermId::fromString($id), $termIds);
        $content = $this->content->byIds(
            $ids,
            $this->languages->forTerms($plan->userId(), $ids, $collectionId),
            // THIS day's collection: a word re-met in yesterday's sentence teaches nothing, so the
            // example written for this day wins inside this day.
            scopeCollectionId: $collectionId,
        );

        // NUMBERS ARE STORED WITH THE DAY AND DEALT BY NOTHING — yet (канон §6, режим NUM-1).
        //
        // They are cards of the day's collection like any other, so they travel with it, get their
        // picture and are there the moment the trainer exists. What they must not be is OWED: a
        // step no session can deal is a stage that never closes, a day that never passes and a day
        // n+1 that is never written. So the plan's own progress does not see them, and neither does
        // the register the day screen draws from it.
        $termIds = array_values(array_filter(
            $termIds,
            static fn (string $id): bool => ($content[$id]->kind ?? null) !== 'number',
        ));
        if ($termIds === []) {
            return new PlanDayProgressView($day->dayIndex(), $collectionId, [], [], [], passed: false);
        }

        $standings = $this->standings->forTerms(
            $plan->userId(),
            $plan->level(),
            $termIds,
            $content,
            $today,
            $tz,
            // WHEN EACH CARD JOINED THIS PLAN — the line under which the review log stops being
            // evidence about it. Read per DAY because that is the granularity the fact has: a plan
            // day is a collection written at one instant, and a card added on day 3 has its own.
            $this->collectionTerms->joinedAtForCollection(
                $plan->userId(),
                $collectionId,
                self::TERMS_PER_DAY_CAP,
            ),
            // WHAT THE OTHER SIDE SAYS in this day, straight off the skeleton. A card that repeats
            // one of these is the interlocutor's whatever `terms.speaker` holds — the model puts an
            // opening line among the day's cards often enough, and it arrives there unmarked
            // ({@see RoleLineModes}).
            self::openingLinesOf($day),
            $yesterday,
        );

        return new PlanDayProgressView(
            index: $day->dayIndex(),
            collectionId: $collectionId,
            termIds: $termIds,
            standings: $standings,
            content: $content,
            passed: $this->stageAClosedForAll($standings, $content),
            sceneIntro: self::sceneText($day, 'intro'),
            sceneTitle: self::sceneText($day, 'title') ?? $day->title(),
            skillOutcomes: self::skillOutcomesOf($day),
        );
    }

    /**
     * One string off the scene of a day's skeleton — its `intro` or its `title`.
     *
     * Read defensively, like {@see openingLinesOf()} and for the same reason: the brief is
     * model-written JSON that has been through four prompt versions, so every level of it is
     * checked rather than assumed. Two shapes of the same fact are accepted — `scene.intro` is what
     * a v0.4 day stores, a top-level `intro` is what the days written before it stored.
     */
    private static function sceneText(PlanDay $day, string $key): ?string
    {
        $brief = $day->roleBrief() ?? [];
        $scene = is_array($brief['scene'] ?? null) ? $brief['scene'] : [];
        $value = $scene[$key] ?? ($brief[$key] ?? null);
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The scene's abilities, keyed by the id a card names in `skill_ref` (канон §8).
     *
     * `outcome` and not `checkpoint`: the outcome is what the learner is there to DO — «рассказать,
     * что болит» — and the checkpoint is how the прогон will know it happened. A card's situation
     * asks for the first.
     *
     * @return array<string, string>
     */
    private static function skillOutcomesOf(PlanDay $day): array
    {
        $brief = $day->roleBrief() ?? [];
        $scene = is_array($brief['scene'] ?? null) ? $brief['scene'] : [];
        $skills = $scene['skills'] ?? ($brief['skills'] ?? null);
        if (! is_array($skills)) {
            return [];
        }

        $out = [];
        foreach ($skills as $skill) {
            if (! is_array($skill)) {
                continue;
            }
            $outcome = $skill['outcome'] ?? null;
            if (! is_string($outcome) || trim($outcome) === '') {
                continue;
            }
            // NO ID, NO ENTRY. A skill written before ids existed is a skill no card can point at —
            // `terms.skill_ref` did not exist either — so inventing a key here would only create a
            // way for a card to be matched to an ability by accident.
            $id = $skill['id'] ?? null;
            if (! is_string($id) || trim($id) === '') {
                continue;
            }
            $out[trim($id)] = trim($outcome);
        }

        return $out;
    }

    /**
     * The day's `role_brief.role.opening_lines`, as plain strings.
     *
     * The same shape {@see \App\Modules\Learning\Application\Query\GetPlanRehearsalHandler::roleOf()}
     * reads, and read defensively for the same reason: the brief is model-written JSON that has been
     * through three prompt versions, so every level of it is checked rather than assumed.
     *
     * @return list<string>
     */
    private static function openingLinesOf(PlanDay $day): array
    {
        $role = $day->roleBrief()['role'] ?? null;
        $lines = is_array($role) ? ($role['opening_lines'] ?? null) : null;
        if (! is_array($lines)) {
            return [];
        }

        $out = [];
        foreach ($lines as $line) {
            $text = is_array($line) ? ($line['text'] ?? null) : $line;
            if (is_string($text) && trim($text) !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * Every card OF THE SCENE has closed stage A.
     *
     * A day with no readable word is NOT passed — an empty checklist is «nothing happened», not
     * «everything happened», and letting it pass would walk the focus through a broken day silently.
     *
     * ## THE RESCUE KIT DOES NOT HOLD A DAY OPEN, and it is day 1's alone that could
     *
     * The five phrases (канон §5) are written into day 1 and dealt in every warm-up after it. They
     * belong to the PLAN rather than to that scene: «день пройден» answers «я прошёл эту ситуацию»,
     * and «Помедленнее, пожалуйста» is not part of the situation — it is what the learner says in
     * all of them.
     *
     * The practical half matters as much as the principle. A card the checklist owes and the
     * assembler cannot build is a step that never closes ({@see PlanStandings}, the fifth filter's
     * own approximation), and every one of those is survivable except on day 1 of every plan: a
     * rescue card in that state would hold day 1 open for ever, and with it the focus, the next
     * day's generation and the whole plan. The kit keeps coming back in the warm-up either way,
     * which is where it is supposed to come back.
     *
     * @param  array<string, PlanTermStanding>  $standings
     * @param  array<string, TermContentView>  $content
     */
    private function stageAClosedForAll(array $standings, array $content): bool
    {
        $scene = array_filter(
            $standings,
            static fn (string $termId): bool => ($content[$termId]->shelf ?? null) !== self::SHELF_RESCUE,
            ARRAY_FILTER_USE_KEY,
        );

        if ($scene === []) {
            return false;
        }

        foreach ($scene as $standing) {
            if ($standing->stage === PlanStage::A && ! $standing->stageComplete) {
                return false;
            }
        }

        return true;
    }

    /** The shelf the server's own five phrases stand on — {@see PlanShelf::Rescue}. */
    private const SHELF_RESCUE = 'rescue';

    /**
     * The first introduction day not yet passed; the FINAL day's index when they all are.
     *
     * @param  list<PlanDay>  $days
     * @param  array<int, PlanDayProgressView>  $progress
     */
    private function focusOf(array $days, array $progress): int
    {
        $lastIndex = 1;
        foreach ($days as $day) {
            $lastIndex = max($lastIndex, $day->dayIndex());
            if ($day->kind() !== PlanDayKind::Intro) {
                continue;
            }
            if (! (isset($progress[$day->dayIndex()]) && $progress[$day->dayIndex()]->passed)) {
                return $day->dayIndex();
            }
        }

        return $lastIndex;
    }
}
