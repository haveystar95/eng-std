<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Exception\PlanActiveLimit;
use App\Modules\Plan\Domain\Exception\PlanSubscriptionRequired;
use App\Modules\Plan\Domain\Service\PlanAllowance;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Paywall;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;

/**
 * «ОДИН ПЛАН, ДЕНЬ 1 БЕСПЛАТНО» (наряд ACC-1 §2) — the rule itself, without a database. Catches the free plan's day 1
 * locked, its day 2 let through, another plan's day 1 let through, a day being walked (or walked) locked under the
 * learner, a second plan without a subscription, and a subscriber's fourth plan in work.
 */

function paywallDay(int $number, DayStatus $status = DayStatus::Locked): PlanDay
{
    return PlanDay::reconstitute(PlanDayId::generate(), PlanId::generate(), $number, DayType::Scene, null, $status, null, null, null, DayMetrics::empty());
}

it('locks nothing while it is open — the switch off, or a subscription', function () {
    $open = Paywall::open();

    expect($open->locks(paywallDay(1)))->toBeFalse()
        ->and($open->locks(paywallDay(2)))->toBeFalse()
        ->and($open->locks(paywallDay(9, DayStatus::Open)))->toBeFalse();
});

it('opens day 1 of the free plan whole and locks every day after it', function () {
    $free = Paywall::withoutSubscription(freePlan: true);

    expect($free->locks(paywallDay(1, DayStatus::Open)))->toBeFalse()
        ->and($free->locks(paywallDay(1)))->toBeFalse()
        ->and($free->locks(paywallDay(2)))->toBeTrue()
        ->and($free->locks(paywallDay(2, DayStatus::Open)))->toBeTrue()
        ->and($free->locks(paywallDay(5)))->toBeTrue();
});

it('locks every day of a plan that is not the free one, day 1 too', function () {
    $other = Paywall::withoutSubscription(freePlan: false);

    expect($other->locks(paywallDay(1, DayStatus::Open)))->toBeTrue()
        ->and($other->locks(paywallDay(2)))->toBeTrue();
});

it('never takes a day away mid-walk: a day in progress and a closed day stay the learner\'s', function () {
    $other = Paywall::withoutSubscription(freePlan: false);

    expect($other->locks(paywallDay(3, DayStatus::InProgress)))->toBeFalse()
        ->and($other->locks(paywallDay(2, DayStatus::Closed)))->toBeFalse();
});

it('gives a learner without a subscription the first plan only, and a subscriber three plans in work', function () {
    PlanAllowance::assertMayCreate(subscribed: false, plansEver: 0, plansInWork: 0);
    PlanAllowance::assertMayCreate(subscribed: true, plansEver: 7, plansInWork: 2);

    expect(fn () => PlanAllowance::assertMayCreate(subscribed: false, plansEver: 1, plansInWork: 0))
        ->toThrow(PlanSubscriptionRequired::class)
        ->and(fn () => PlanAllowance::assertMayCreate(subscribed: true, plansEver: 3, plansInWork: 3))
        ->toThrow(PlanActiveLimit::class);

    $refused = null;
    try {
        PlanAllowance::assertMayCreate(subscribed: false, plansEver: 1, plansInWork: 0);
    } catch (PlanSubscriptionRequired $e) {
        $refused = $e;
    }
    $capped = null;
    try {
        PlanAllowance::assertMayCreate(subscribed: true, plansEver: 3, plansInWork: 3);
    } catch (PlanActiveLimit $e) {
        $capped = $e;
    }
    expect($refused?->problemStatus())->toBe(402)
        ->and($refused?->problemCode())->toBe('plan_subscription_required')
        ->and($capped?->problemStatus())->toBe(409)
        ->and($capped?->problemCode())->toBe('plan_active_limit')
        ->and($capped?->problemMeta())->toBe(['limit' => 3, 'plans_in_work' => 3]);
});
