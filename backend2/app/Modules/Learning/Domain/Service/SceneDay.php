<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * HOW BIG A DAY-SCENE IS — one number, and the reason it is no longer three.
 *
 * Until v0.4 the size of a day was arithmetic over the learner's minutes: {@see DayCapacity}
 * turned «20 минут» into fourteen cards and split them 8/2/4, and P2 was handed those three exact
 * counts. The canon replaced that whole mechanism with a shape (`docs/plan-model.md` §2): a day is
 * one SCENE, and a scene holds four to six lines you will hear, four to six you will say, two or
 * three you will ask, six to eight pieces they are built from, and two to four numbers.
 *
 *     «Итого день ≈ 25 единиц за те же ~20 минут.»
 *
 * ## Why more cards fit into the same twenty minutes
 *
 * They are not the same cards. A v0.3 day was fourteen sentences to memorise; a scene is a handful
 * of frames and the pieces that go into their holes, and the two touches a `hear` line gets are
 * shorter than the four a spoken one does. The minutes are still the learner's own — what they now
 * bound is the SESSION ({@see \App\Modules\Learning\Application\Command\BuildPlanSessionHandler}),
 * which deals as many cards as the minutes hold and leaves the rest for the next sitting.
 *
 * ## What this number is NOT
 *
 * It is not a demand on the model. P2 v0.4 is handed shelf guides in prose and its answer is
 * counted against them as WARNINGS — a day that comes back with 22 units or 27 is a day, not an
 * error, and the три числа that made «one card off» a refusal are gone. What this number is used
 * for is everything the SERVER has to say before the day exists: the plan preview («~75 фраз и
 * слов»), the day's `term_budget` row, and the readiness denominator, which has to be the whole
 * plan from day one or the percentage falls as the learner works (Д-31).
 */
final class SceneDay
{
    /**
     * The canon's own number: about twenty-five units in a day-scene.
     *
     * The midpoint of the five shelf guides is 22–23; the canon rounds it to 25 and that is the
     * number quoted at the learner, so it is the number stored. One place, because a preview that
     * promises one figure and a day that plans another is the disagreement `DayCapacity` was
     * written to end.
     */
    public const UNITS = 25;

    /**
     * How many units a day of this scene plans for.
     *
     * A parameter list of one and a constant answer, deliberately: the size of a scene is a
     * property of the SCENE — «одна стойка, одна комната, один звонок» — and not of how many
     * abilities the model happened to price into it. A scene with two skills is still a scene, and
     * a day that shrank because its scene was tidy would teach the learner less for no reason they
     * chose.
     */
    public static function units(): int
    {
        return self::UNITS;
    }
}
