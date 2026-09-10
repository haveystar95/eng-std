<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * «Убрать из изучения»: clear `enrolled_at` and change nothing else.
 *
 * There is no branch here for «and also reset the ladder» or «and also drop the due date», and that
 * absence is the feature. The word stops being dealt; everything the learner earned on it stands.
 *
 * A pair with no row at all is not an error — it was never in the pool, so the request is already
 * satisfied. Returns whether this call was the one that changed anything, so the client can tell a
 * real removal from a replayed one.
 */
final readonly class UnenrollTermHandler
{
    public function __construct(
        private TermProgressRepository $progress,
        private TransactionManager $tx,
    ) {}

    public function __invoke(UnenrollTerm $command): bool
    {
        return $this->tx->run(function () use ($command): bool {
            $existing = $this->progress->findForUpdate($command->actorId, $command->termId);
            if ($existing === null || ! $existing->isEnrolled()) {
                return false;
            }

            $this->progress->save($existing->unenroll());

            return true;
        });
    }
}
