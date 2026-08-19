<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;

/**
 * «Учить это слово»: stamp `enrolled_at` on the (user, term) pair, creating the row if the learner
 * has never met the word.
 *
 * IDEMPOTENT on two levels, because this arrives from a phone with an offline queue: the entity
 * keeps the first enrolment moment ({@see TermProgress::enroll()}), and a pair that is already in
 * the pool is not written at all — so a double tap does not bump `updated_at` and does not push a
 * meaningless row into every device's next `/sync` page.
 *
 * A pair that was PAUSED and is being brought back lands here too, and this is the case the whole
 * design is arranged around: the row already holds its rung, its counter and its due date, and none
 * of them is touched. The word resumes; it does not restart.
 *
 * Returns whether this call was the one that changed anything.
 */
final readonly class EnrollTermHandler
{
    public function __construct(
        private TermProgressRepository $progress,
        private TermExistenceReader $terms,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(EnrollTerm $command): bool
    {
        if ($this->terms->existing([$command->termId]) === []) {
            return false;
        }

        return $this->tx->run(function () use ($command): bool {
            $existing = $this->progress->findForUpdate($command->actorId, $command->termId);
            if ($existing !== null && $existing->isEnrolled()) {
                return false;
            }

            $progress = $existing ?? TermProgress::start($command->actorId, $command->termId);
            $this->progress->save($progress->enroll($this->clock->now()));

            return true;
        });
    }
}
