<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\GenerationDailyLimit;
use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;

it('maps each tier to its daily generation allowance', function () {
    $limits = new GenerationDailyLimit();

    expect($limits->forTier(SubscriptionTier::Free))->toBe(3)
        ->and($limits->forTier(SubscriptionTier::Premium))->toBe(20);
});
