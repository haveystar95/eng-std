<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Query\GetGenerationQuota;
use App\Modules\Generation\Application\Query\GetGenerationQuotaHandler;
use App\Modules\Generation\Domain\Service\GenerationDailyLimit;
use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\FakeGenerationQuota;
use Tests\Doubles\FakeUserTierReader;
use Tests\Doubles\FixedClock;

it('reports remaining against the free daily limit and the next UTC reset', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-08-04T16:30:00Z'));
    $handler = new GetGenerationQuotaHandler(
        new FakeGenerationQuota(2), $clock, new FakeUserTierReader(SubscriptionTier::Free), new GenerationDailyLimit(),
    );

    $view = $handler(new GetGenerationQuota(UserId::generate()));

    expect($view->limit)->toBe(GenerationDailyLimit::FREE)
        ->and($view->used)->toBe(2)
        ->and($view->remaining)->toBe(GenerationDailyLimit::FREE - 2)
        ->and($view->resetsAt->format(DATE_ATOM))->toBe('2026-08-05T00:00:00+00:00');
});

it('reports the higher premium limit for a premium user', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-08-04T16:30:00Z'));
    $handler = new GetGenerationQuotaHandler(
        new FakeGenerationQuota(5), $clock, new FakeUserTierReader(SubscriptionTier::Premium), new GenerationDailyLimit(),
    );

    $view = $handler(new GetGenerationQuota(UserId::generate()));

    expect($view->limit)->toBe(GenerationDailyLimit::PREMIUM)
        ->and($view->remaining)->toBe(GenerationDailyLimit::PREMIUM - 5);
});

it('never reports negative remaining when the quota is over-spent', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-08-04T16:30:00Z'));
    $handler = new GetGenerationQuotaHandler(
        new FakeGenerationQuota(GenerationDailyLimit::FREE + 5), $clock,
        new FakeUserTierReader(SubscriptionTier::Free), new GenerationDailyLimit(),
    );

    $view = $handler(new GetGenerationQuota(UserId::generate()));

    expect($view->remaining)->toBe(0);
});
