<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Exception\ReferenceLanguageTerm;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;
use App\Modules\Vocabulary\Application\Query\TermLanguageReader;

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
 *
 * THE ONE THING IT REFUSES is a word of a reference language ({@see ReferenceLanguageTerm}): zh and
 * ja carry no trainer, so the pool has nothing to do with them. The gate stands HERE, at the act
 * itself, rather than in each caller — `PUT /pool/terms/{id}` and the save from search
 * ({@see \App\Modules\Generation\Application\Command\AddSearchResultHandler}) both come through this
 * handler, and a second copy of the rule beside each of them is how the two would drift apart.
 */
final readonly class EnrollTermHandler
{
    public function __construct(
        private TermProgressRepository $progress,
        private TermExistenceReader $terms,
        private TermLanguageReader $termLangs,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    /** @throws ReferenceLanguageTerm when the term's language carries no trainers at all */
    public function __invoke(EnrollTerm $command): bool
    {
        if ($this->terms->existing([$command->termId]) === []) {
            return false;
        }

        // Read AFTER existence: a term that is not there is «nothing happened» (the offline queue
        // replays deletions), while a term that is there in a language we cannot teach is a refusal.
        $lang = $this->termLangs->langsFor([$command->termId])[$command->termId->value] ?? null;
        if ($lang !== null && LanguageRoles::isReference($lang)) {
            throw ReferenceLanguageTerm::make($command->termId, $lang);
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
