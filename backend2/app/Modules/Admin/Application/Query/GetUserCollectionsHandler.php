<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\AdminCollectionProgress;
use App\Modules\Admin\Application\Dto\UserCollectionWithProgress;
use App\Modules\Admin\Application\Port\AdminUserReader;
use App\Modules\Learning\Application\Dto\CollectionProgressView;
use App\Modules\Learning\Application\Query\GetCollectionsProgress;
use App\Modules\Learning\Application\Query\GetCollectionsProgressHandler;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * The user's collections (Admin projection) merged with their per-collection progress from Learning
 * (Mastery-defined — Admin never computes «усвоено» itself). Read-only. Null when the user is unknown.
 */
final readonly class GetUserCollectionsHandler
{
    public function __construct(
        private AdminUserReader $users,
        private GetCollectionsProgressHandler $collectionsProgress,
        private Clock $clock,
    ) {}

    /** @return list<UserCollectionWithProgress>|null */
    public function __invoke(GetUserCollections $query): ?array
    {
        if ($this->users->profile($query->userId->value) === null) {
            return null;
        }

        $collections = $this->users->collectionsOf($query->userId->value);

        /** @var array<string, CollectionProgressView> $progressById */
        $progressById = [];
        foreach (($this->collectionsProgress)(new GetCollectionsProgress($query->userId, $this->clock->now())) as $view) {
            $progressById[$view->collectionId] = $view;
        }

        $out = [];
        foreach ($collections as $c) {
            $p = $progressById[$c->id] ?? null;
            $out[] = new UserCollectionWithProgress(
                id: $c->id,
                title: $c->title,
                type: $c->type,
                itemsCount: $c->itemsCount,
                addedAt: $c->addedAt,
                progress: $p !== null
                    ? new AdminCollectionProgress(
                        total: $p->total,
                        newCount: $p->newCount,
                        inProgress: $p->inProgress,
                        mastered: $p->mastered,
                        confirmed: $p->confirmed,
                        familiar: $p->familiar,
                        due: $p->due,
                    )
                    // A collection with no computed progress (e.g. no terms) → all not-started.
                    : new AdminCollectionProgress($c->itemsCount, $c->itemsCount, 0, 0, 0, 0, 0),
            );
        }

        return $out;
    }
}
