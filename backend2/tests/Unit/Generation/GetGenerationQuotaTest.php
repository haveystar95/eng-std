<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Query\GetGenerationQuota;
use App\Modules\Generation\Application\Query\GetGenerationQuotaHandler;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\FakeGenerationQuota;
use Tests\Doubles\FixedClock;

it('reports remaining against the daily limit and the next UTC reset', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-08-04T16:30:00Z'));
    $handler = new GetGenerationQuotaHandler(new FakeGenerationQuota(3), $clock);

    $view = $handler(new GetGenerationQuota(UserId::generate()));

    expect($view->limit)->toBe(RequestCollectionGenerationHandler::DAILY_LIMIT)
        ->and($view->used)->toBe(3)
        ->and($view->remaining)->toBe(RequestCollectionGenerationHandler::DAILY_LIMIT - 3)
        ->and($view->resetsAt->format(DATE_ATOM))->toBe('2026-08-05T00:00:00+00:00');
});

it('never reports negative remaining when the quota is over-spent', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-08-04T16:30:00Z'));
    $handler = new GetGenerationQuotaHandler(
        new FakeGenerationQuota(RequestCollectionGenerationHandler::DAILY_LIMIT + 5),
        $clock,
    );

    $view = $handler(new GetGenerationQuota(UserId::generate()));

    expect($view->remaining)->toBe(0);
});
