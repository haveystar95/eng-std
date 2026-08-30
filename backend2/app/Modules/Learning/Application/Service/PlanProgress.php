<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\TermId;
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
 * One collection read, one content read and one review-log read per introduction day — up to
 * fourteen of each ({@see \App\Modules\Learning\Domain\Service\PlanScheduler::MAX_INTRO_DAYS}). The
 * content read is per DAY rather than for the whole plan on purpose: a term's example is chosen
 * through the collection it is being shown in, and «сначала пример этого дня, иначе общий» is only
 * expressible one scope at a time.
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
        $today = $this->clock->now()->setTimezone($tz)->format('Y-m-d');

        $progress = [];
        foreach ($days as $day) {
            if ($day->kind() !== PlanDayKind::Intro) {
                continue;
            }
            $progress[$day->dayIndex()] = $this->dayProgress($plan, $day, $today, $tz);
        }

        return new PlanProgressView(
            days: $progress,
            focusDayIndex: $this->focusOf($days, $progress),
            today: $today,
        );
    }

    private function dayProgress(LearningPlan $plan, PlanDay $day, string $today, \DateTimeZone $tz): PlanDayProgressView
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

        $standings = $this->standings->forTerms(
            $plan->userId(),
            $plan->level(),
            $termIds,
            $content,
            $today,
            $tz,
        );

        return new PlanDayProgressView(
            index: $day->dayIndex(),
            collectionId: $collectionId,
            termIds: $termIds,
            standings: $standings,
            content: $content,
            passed: $this->stageAClosedForAll($standings),
        );
    }

    /**
     * Every word of the day has closed stage A.
     *
     * A day with no readable word is NOT passed — an empty checklist is «nothing happened», not
     * «everything happened», and letting it pass would walk the focus through a broken day silently.
     *
     * @param  array<string, PlanTermStanding>  $standings
     */
    private function stageAClosedForAll(array $standings): bool
    {
        if ($standings === []) {
            return false;
        }

        foreach ($standings as $standing) {
            if ($standing->stage === PlanStage::A && ! $standing->stageComplete) {
                return false;
            }
        }

        return true;
    }

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
