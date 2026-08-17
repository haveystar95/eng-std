<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\StatsView;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\StatsReader;

final readonly class GetUserStatsHandler
{
    public function __construct(
        private StatsReader $stats,
        private LearnerProfileReader $profile,
        private IntroducedTermsReader $introduced,
    ) {}

    public function __invoke(GetUserStats $query): StatsView
    {
        // Activity is computed in the user's calendar day (device-batch F19/F18): an evening review
        // belongs to that local day, and the streak/day-lit follow the user's zone, not UTC.
        //
        // The new-term quota is surfaced with the SAME figures the session builder uses (F13), so a
        // "Learn N" CTA can only offer what the next session would actually introduce: the clamped
        // daily goal and today's introductions on the same accounting GetDueTerms reads.
        return $this->stats->read(
            $query->userId,
            $query->now,
            $this->profile->timezoneFor($query->userId),
            $this->profile->newTermsPerDay($query->userId),
            $this->introduced->countForDay($query->userId, $query->now),
        );
    }
}
