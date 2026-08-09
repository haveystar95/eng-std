<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\AdminUserDetail;
use App\Modules\Admin\Application\Port\AdminCostReader;
use App\Modules\Admin\Application\Port\AdminUserReader;
use App\Modules\Learning\Application\Query\GetUserStats;
use App\Modules\Learning\Application\Query\GetUserStatsHandler;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * Assembles the user detail from three sources, each the owner of its truth:
 *  - Admin projections (profile, raw state tally, review total, library);
 *  - Learning (mastered/learned/due — the Mastery-defined counts and streak), via its Application;
 *  - the AI-spend ledgers (per-category cost).
 * Read-only.
 */
final readonly class GetUserDetailHandler
{
    public function __construct(
        private AdminUserReader $users,
        private AdminCostReader $costs,
        private GetUserStatsHandler $stats,
        private Clock $clock,
    ) {}

    public function __invoke(GetUserDetail $query): ?AdminUserDetail
    {
        $userId = $query->userId->value;

        $profile = $this->users->profile($userId);
        if ($profile === null) {
            return null;
        }

        $stats = ($this->stats)(new GetUserStats($query->userId, $this->clock->now()));

        return new AdminUserDetail(
            profile: $profile,
            states: $this->users->progressStates($userId),
            mastered: $stats->mastered,
            learned: $stats->learned,
            dueToday: $stats->dueToday,
            reviewsTotal: $this->users->reviewsTotal($userId),
            reviewsToday: $stats->reviewsToday,
            streakDays: $stats->streakDays,
            costs: $this->costs->userBreakdownSince($userId, null),
            collections: $this->users->collectionsOf($userId),
        );
    }
}
