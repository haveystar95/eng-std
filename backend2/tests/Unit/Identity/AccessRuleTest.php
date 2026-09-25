<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Service\AccessRule;
use App\Modules\Identity\Domain\ValueObject\AccessPlan;
use App\Modules\Identity\Domain\ValueObject\Entitlement;
use App\Modules\Identity\Domain\ValueObject\EntitlementProduct;
use App\Modules\Identity\Domain\ValueObject\EntitlementSource;
use App\Modules\Identity\Domain\ValueObject\EntitlementStatus;

/**
 * THE LEARNER'S ACCESS (наряд ACC-1 §2): «активная подписка — status active | grace, expires_at в будущем или null». Read
 * over every right the learner has; when several are in force, the longest names the end and the source. Catches an
 * expired row taken for a subscription, a grace period refused, an end in the past let through, and «until when» taken
 * from the right that ends first.
 */

function accessRight(EntitlementStatus $status, ?string $expires, EntitlementSource $source = EntitlementSource::Admin, EntitlementProduct $product = EntitlementProduct::Month): Entitlement
{
    $at = new DateTimeImmutable('2026-09-01T00:00:00+00:00');

    return new Entitlement($source, $product, $status, $at, $expires === null ? null : new DateTimeImmutable($expires), $at);
}

it('reads a right in force: active or grace, and no end or an end still ahead', function (EntitlementStatus $status, ?string $expires, bool $premium) {
    $now = new DateTimeImmutable('2026-09-25T12:00:00+00:00');
    $access = AccessRule::of([accessRight($status, $expires)], $now);

    expect($access->plan)->toBe($premium ? AccessPlan::Premium : AccessPlan::Free)
        ->and($access->isPremium())->toBe($premium);
})->with([
    'active, no end' => [EntitlementStatus::Active, null, true],
    'active, ends tomorrow' => [EntitlementStatus::Active, '2026-09-26T00:00:00+00:00', true],
    'grace, ends tomorrow' => [EntitlementStatus::Grace, '2026-09-26T00:00:00+00:00', true],
    'active, ended an hour ago' => [EntitlementStatus::Active, '2026-09-25T11:00:00+00:00', false],
    'active, ends this very moment' => [EntitlementStatus::Active, '2026-09-25T12:00:00+00:00', false],
    'expired, no end' => [EntitlementStatus::Expired, null, false],
    'expired, ends tomorrow' => [EntitlementStatus::Expired, '2026-09-26T00:00:00+00:00', false],
]);

it('is the free plan with no right at all, with nothing to say about an end or a source', function () {
    $access = AccessRule::of([], new DateTimeImmutable('2026-09-25T12:00:00+00:00'));

    expect($access->plan)->toBe(AccessPlan::Free)
        ->and($access->expiresAt)->toBeNull()
        ->and($access->source)->toBeNull();
});

it('names the right that lasts longest among those in force — no end outlasts any end', function () {
    $now = new DateTimeImmutable('2026-09-25T12:00:00+00:00');
    $month = accessRight(EntitlementStatus::Active, '2026-10-25T00:00:00+00:00', EntitlementSource::Apple);
    $year = accessRight(EntitlementStatus::Active, '2027-09-25T00:00:00+00:00', EntitlementSource::Promo, EntitlementProduct::Year);
    $lifetime = accessRight(EntitlementStatus::Active, null, EntitlementSource::Admin, EntitlementProduct::Lifetime);
    $gone = accessRight(EntitlementStatus::Expired, null, EntitlementSource::Google, EntitlementProduct::Lifetime);

    $twoEnds = AccessRule::of([$month, $year], $now);
    $withLifetime = AccessRule::of([$month, $lifetime, $year], $now);
    $withGone = AccessRule::of([$gone, $month], $now);

    expect($twoEnds->source)->toBe(EntitlementSource::Promo)
        ->and($twoEnds->expiresAt?->format('Y-m-d'))->toBe('2027-09-25')
        ->and($withLifetime->source)->toBe(EntitlementSource::Admin)
        ->and($withLifetime->expiresAt)->toBeNull()
        ->and($withGone->source)->toBe(EntitlementSource::Apple);
});

it('gives a product its term: a month, a year, no end for good', function () {
    $start = new DateTimeImmutable('2026-09-15T10:00:00+00:00');

    expect(EntitlementProduct::Month->expiresAfter($start)?->format(DATE_ATOM))->toBe('2026-10-15T10:00:00+00:00')
        ->and(EntitlementProduct::Year->expiresAfter($start)?->format(DATE_ATOM))->toBe('2027-09-15T10:00:00+00:00')
        ->and(EntitlementProduct::Lifetime->expiresAfter($start))->toBeNull();
});
