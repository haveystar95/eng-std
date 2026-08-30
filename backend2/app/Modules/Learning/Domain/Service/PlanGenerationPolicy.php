<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;

/**
 * WHEN A PLAN SPENDS MONEY — the whole policy, in one pure place.
 *
 * Every day of a plan is a paid model call, and the two ways of getting this wrong are opposite.
 * Generate everything at the start and a learner who abandons on day two has paid for fourteen days
 * nobody will ever open. Generate strictly one day ahead and a fourteen-day plan makes the learner
 * wait for a vendor round trip every single morning.
 *
 * So the policy splits on the one number that decides which mistake is cheaper:
 *
 * **A short plan ({@see EAGER_INTRO_DAYS} days or fewer) is written whole, at the start.** Three
 * days is a plan somebody either does or does not do; there is no meaningful abandonment window,
 * and the alternative — a learner opening day 2 tomorrow and watching a spinner — is a worse
 * product for the sake of at most two calls.
 *
 * **A longer plan is written one day at a time, and the next day is queued when the previous one is
 * DONE.** Done, not ready: «ready» means the material exists, «done» means the learner has actually
 * walked it, and it is the second one that is evidence they will come back. This is also what makes
 * a broken day stop the plan instead of producing five more broken days.
 *
 * **On demand, anything, one at a time.** A learner may look ahead — the day exists in the skeleton,
 * and refusing to build it would be pretending it does not. What that path may NOT do is run away:
 * at most {@see MAX_READY_AHEAD} days may stand written-or-writing ahead of the day the learner is
 * on, and only one may be generating at any moment.
 */
final class PlanGenerationPolicy
{
    /** At or below this many introduction days, the whole plan is written at the start. */
    public const EAGER_INTRO_DAYS = 3;

    /**
     * How many days may stand ready or generating AHEAD of the focus.
     *
     * The plan's own spending ceiling, and the answer to the PLAN-1a tail «у плана нет своего
     * лимита трат». It is expressed against the FOCUS rather than as a total, because what it is
     * protecting against is not an expensive plan — a fourteen-day plan is legitimately fourteen
     * calls — but a plan that paid for days the learner never reached.
     */
    public const MAX_READY_AHEAD = 2;

    /** Is this plan short enough to be written whole at the start? */
    public static function generatesEagerly(int $introDays): bool
    {
        return $introDays <= self::EAGER_INTRO_DAYS;
    }

    /**
     * The introduction days to queue the moment the plan starts.
     *
     * @param  list<PlanDay>  $days
     * @return list<int>  day indexes, in order
     */
    public static function daysToQueueAtStart(array $days, int $introDays): array
    {
        $pending = [];
        foreach ($days as $day) {
            if ($day->kind() === PlanDayKind::Intro && ! $day->isReady()) {
                $pending[] = $day->dayIndex();
            }
        }

        if ($pending === []) {
            return [];
        }

        return self::generatesEagerly($introDays) ? $pending : [$pending[0]];
    }

    /**
     * The next day to queue now that `$doneDayIndex` has been walked, or null when nothing is owed.
     *
     * Null on a short plan is the correct answer and not an omission: everything was queued at the
     * start, so there is nothing left to chain.
     *
     * @param  list<PlanDay>  $days
     */
    public static function nextAfterDone(array $days, int $doneDayIndex, int $focusDayIndex): ?int
    {
        foreach ($days as $day) {
            if ($day->kind() !== PlanDayKind::Intro || $day->dayIndex() <= $doneDayIndex) {
                continue;
            }
            if ($day->status() !== PlanDayStatus::Pending) {
                continue;
            }

            return self::hasRoomAhead($days, $focusDayIndex) ? $day->dayIndex() : null;
        }

        return null;
    }

    /**
     * May another day be generated ahead of the focus right now?
     *
     * Two conditions, and they answer different worries: the CEILING stops the plan paying for days
     * the learner has not reached, and «nothing is generating» stops two on-demand taps from
     * buying two days at once.
     *
     * @param  list<PlanDay>  $days
     */
    public static function hasRoomAhead(array $days, int $focusDayIndex): bool
    {
        $ahead = 0;
        foreach ($days as $day) {
            if ($day->kind() !== PlanDayKind::Intro || $day->dayIndex() <= $focusDayIndex) {
                continue;
            }
            if ($day->status() === PlanDayStatus::Generating) {
                return false;
            }
            if ($day->status() === PlanDayStatus::Ready) {
                $ahead++;
            }
        }

        return $ahead < self::MAX_READY_AHEAD;
    }
}
