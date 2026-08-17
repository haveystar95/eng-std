<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Generation\Application\Dto\GenerationQuotaView;
use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Domain\Service\GenerationDailyLimit;
use App\Modules\Identity\Application\Port\UserTierReader;
use App\Modules\Shared\Domain\Service\Clock;
use DateTimeZone;

final readonly class GetGenerationQuotaHandler
{
    public function __construct(
        private GenerationQuota $quota,
        private Clock $clock,
        private UserTierReader $tiers,
        private GenerationDailyLimit $limits,
    ) {}

    public function __invoke(GetGenerationQuota $query): GenerationQuotaView
    {
        $now = $this->clock->now();
        $limit = $this->limits->forTier($this->tiers->tierOf($query->userId));
        $used = $this->quota->usedOn($query->userId, $now);

        // Next UTC-day boundary — the same window EloquentGenerationQuota counts against, so
        // "remaining" and "resets_at" describe one and the same reset. Absolute instant on purpose:
        // the client renders it locally, no stored timezone needed.
        $resetsAt = $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0)->modify('+1 day');

        return new GenerationQuotaView(
            limit: $limit,
            used: $used,
            remaining: max(0, $limit - $used),
            resetsAt: $resetsAt,
        );
    }
}
