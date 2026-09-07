<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;

/**
 * WHEN A PLAN SPENDS MONEY — the whole policy, in one pure place.
 *
 * Every day of a plan is a paid model call, and there is now ONE rule for every plan, short or
 * long: **the next day is written when the previous one is DONE** — walked by the learner, all
 * three of its stages passed through ({@see \App\Modules\Learning\Domain\Service\PlanDayPassage}).
 * Done, not ready: «ready» means the material exists, «done» means somebody actually did it, and it
 * is the second one that is evidence they will come back. It is also what makes a broken day stop a
 * plan instead of producing five more broken days.
 *
 * ## Короткий план больше НЕ пишется целиком на старте (наряд DAY-GATE-1, Ч.1.3)
 *
 * Правило «план из трёх дней и короче пишется весь при старте» отменено решением владельца 07.09,
 * и отменено вместе с тем, ради чего оно существовало. Оно покупало одно — чтобы человек не смотрел
 * на спиннер утром второго дня, — и продавало два: план платил за дни, до которых можно не дойти
 * (живой прогон 07.09 написал три дня за девяносто секунд, а пройден был один), и день N+1
 * писался, когда о дне N не было известно НИЧЕГО, кроме того, что он существует.
 *
 * Ожидание никуда не делось, оно стало честным: день N+1 встаёт в очередь в тот момент, когда
 * закрыт день N, и человек видит экран сборки, а не пустую строку. Замок дня ({@see \App\Modules\Learning\Domain\ValueObject\PlanDayStage})
 * и так не пустил бы его дальше.
 *
 * ## Один день за раз, и это не про скорость
 *
 * Первая версия этого класса выстреливала весь короткий план одним залпом, и живой прогон S1
 * показал цену: вызов дня 2 стартовал через секунду после дня 1 и закончился раньше, чем появилась
 * коллекция дня 1, поэтому `KnownTermsReader::metInPlan` вернул пусто, блок KNOWN ушёл в модель
 * пустым, и день 2 был написан так, будто дня 1 не было. Ничего не упало: день был валиден, гейту
 * связности не с чем было сравнивать, и единственным следом было то, что ни один из девяти терминов
 * дня 1 не получил свежего примера. День N пишется ИЗ дней 1…N−1 — это последовательность, и
 * веерный запуск не быстрый способ её пройти, а способ её не проходить.
 *
 * **On demand, anything, one at a time.** A learner may look ahead — the day exists in the skeleton,
 * and refusing to build it would be pretending it does not. What that path may NOT do is run away:
 * at most {@see MAX_READY_AHEAD} days may stand written-or-writing ahead of the day the learner is
 * on, and only one may be generating at any moment.
 */
final class PlanGenerationPolicy
{
    /**
     * How many days may stand ready or generating AHEAD of the focus.
     *
     * The plan's own spending ceiling, and the answer to the PLAN-1a tail «у плана нет своего
     * лимита трат». It is expressed against the FOCUS rather than as a total, because what it is
     * protecting against is not an expensive plan — a fourteen-day plan is legitimately fourteen
     * calls — but a plan that paid for days the learner never reached.
     */
    public const MAX_READY_AHEAD = 2;

    /**
     * The FIRST day to queue when the plan starts — one, on every plan.
     *
     * The only day a plan pays for before anybody has done anything: without it there would be
     * nothing to open. Everything after it is carried by {@see nextAfterDone()}.
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
     * THE NEXT DAY TO QUEUE now that `$doneDayIndex` has been WALKED — на любом плане, длинном и
     * коротком (наряд DAY-GATE-1, Ч.1.3).
     *
     * Единственная цепочка, которая ведёт план вперёд. Раньше их было две, и вторая («короткий план
     * целиком на старте») отменена вместе с ветвлением по длине: см. докблок класса.
     *
     * @param  list<PlanDay>  $days
     */
    public static function nextAfterDone(array $days, int $doneDayIndex, int $focusDayIndex): ?int
    {
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
