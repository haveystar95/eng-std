<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\AdminLadderCounts;
use App\Modules\Admin\Application\Dto\AdminLadderEvent;
use App\Modules\Admin\Application\Dto\AdminLadderFilter;
use App\Modules\Admin\Application\Dto\AdminLadderLearner;
use App\Modules\Admin\Application\Dto\AdminLadderPairFacts;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;

/**
 * The live-observation projection: what each of a learner's words is doing right now, and what
 * their device has just done. Strictly read-only — nothing behind this port writes.
 */
interface AdminLadderReader
{
    /** @return list<AdminLadderLearner> most recently active first */
    public function learners(int $limit): array;

    /** @return Page<AdminLadderPairFacts> ordered by last answer, most recent first */
    public function pairs(string $userId, AdminLadderFilter $filter, ListWindow $window): Page;

    /** The phase split for the same learner, narrowed by collection only — never by phase itself. */
    public function counts(string $userId, ?string $collectionId): AdminLadderCounts;

    /** @return list<AdminLadderEvent> newest first */
    public function events(string $userId, int $limit): array;
}
