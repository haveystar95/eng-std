<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query;

use App\Modules\Identity\Application\Dto\UsualVisitTimeView;
use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Identity\Application\Port\VisitLog;
use App\Modules\Identity\Domain\Service\UsualVisitTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * The learner's usual time of day: the last seven visits read in `profiles.timezone` (the zone
 * the learner's calendar already lives in), the median, down to the quarter hour — the rule is
 * {@see UsualVisitTime}. The zone is today's, not the one of each visit: a learner who moved
 * reads their old visits at the new clock, which is what a reminder for the new clock wants.
 */
final readonly class GetUsualVisitTimeHandler
{
    public function __construct(
        private VisitLog $visits,
        private UserReader $users,
    ) {}

    public function __invoke(GetUsualVisitTime $query): UsualVisitTimeView
    {
        $zone = $this->zone($query);
        $minutes = array_map(
            static fn (DateTimeImmutable $at): int => UsualVisitTime::minutesOf($at->setTimezone($zone)),
            $this->visits->latest($query->userId, UsualVisitTime::WINDOW),
        );

        return new UsualVisitTimeView(UsualVisitTime::of($minutes), $zone->getName(), count($minutes));
    }

    private function zone(GetUsualVisitTime $query): DateTimeZone
    {
        $name = $this->users->byId($query->userId)?->profile->timezone ?? 'UTC';
        try {
            return new DateTimeZone($name);
        } catch (Exception) {
            return new DateTimeZone('UTC');
        }
    }
}
