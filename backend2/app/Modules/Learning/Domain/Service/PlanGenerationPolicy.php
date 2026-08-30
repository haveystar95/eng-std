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
 * ## «Whole» never means «at once», and that distinction cost a live run
 *
 * Both branches queue exactly ONE day at a time. The eager branch differs only in WHAT it waits
 * for — `ready` instead of `done` — so a short plan is written end to end in one sitting without a
 * learner in the loop.
 *
 * The first version of this class fanned the whole short plan out in a single dispatch, and the
 * live S1 run showed what that costs: day 2's model call started one second after day 1's and
 * finished before day 1's collection existed, so `KnownTermsReader::metInPlan` returned nothing,
 * the prompt's KNOWN block went out empty, and day 2 was written as if day 1 had never happened.
 * Nothing failed — the day was valid, the coherence gate had nothing to compare against, and the
 * only visible trace was that not one of day 1's nine terms got the fresh example day 2 was
 * supposed to give it. Day n is written FROM days 1…n−1; that is the sequence, and a fan-out is
 * not a faster way to walk a sequence, it is a way to not walk it.
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
     * The FIRST day to queue when the plan starts — one, on every plan.
     *
     * Both branches start the same way and differ only in what carries the chain forward
     * ({@see nextAfterReady()} on a short plan, {@see nextAfterDone()} on a long one). See the class
     * docblock for why «written whole» must not mean «written at once».
     *
     * @param  list<PlanDay>  $days
     */
    public static function firstDayToQueue(array $days): ?int
    {
        foreach ($days as $day) {
            if ($day->kind() === PlanDayKind::Intro && ! $day->isReady()) {
                return $day->dayIndex();
            }
        }

        return null;
    }

    /**
     * SHORT PLAN ONLY: the next day to queue now that `$readyDayIndex` has been written.
     *
     * This is what makes a three-day plan arrive whole without a learner in the loop — and it fires
     * on `ready` rather than on `done` precisely because there is no learner in the loop yet.
     *
     * Null on a long plan, where the chain is carried by {@see nextAfterDone()} instead: writing
     * day 5 because day 4 came back would spend the whole plan's budget on the afternoon it
     * started, which is the thing the split exists to prevent.
     *
     * @param  list<PlanDay>  $days
     */
    public static function nextAfterReady(array $days, int $readyDayIndex, int $introDays): ?int
    {
        if (! self::generatesEagerly($introDays)) {
            return null;
        }

        return self::nextPendingAfter($days, $readyDayIndex);
    }

    /**
     * LONG PLAN ONLY: the next day to queue now that `$doneDayIndex` has been WALKED.
     *
     * Null on a short plan is the correct answer and not an omission: its chain already ran on
     * `ready` and the whole plan is written.
     *
     * @param  list<PlanDay>  $days
     */
    public static function nextAfterDone(array $days, int $doneDayIndex, int $focusDayIndex, int $introDays): ?int
    {
        if (self::generatesEagerly($introDays)) {
            return null;
        }

        $next = self::nextPendingAfter($days, $doneDayIndex);

        return $next !== null && self::hasRoomAhead($days, $focusDayIndex) ? $next : null;
    }

    /**
     * The first introduction day after `$after` that still needs writing.
     *
     * `pending` only: a day that is `generating` is already somebody's, and one that is `failed`
     * has spent both its attempts and must not be picked up by a chain — the learner asking for it
     * by hand is a different decision.
     *
     * @param  list<PlanDay>  $days
     */
    private static function nextPendingAfter(array $days, int $after): ?int
    {
        foreach ($days as $day) {
            if ($day->kind() !== PlanDayKind::Intro || $day->dayIndex() <= $after) {
                continue;
            }

            return $day->status() === PlanDayStatus::Pending ? $day->dayIndex() : null;
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
