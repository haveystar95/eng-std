<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Dto\SessionView;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Query\GetDueTerms;
use App\Modules\Learning\Application\Query\GetDueTermsHandler;
use App\Modules\Learning\Application\Query\GetPracticeTerms;
use App\Modules\Learning\Application\Query\GetPracticeTermsHandler;
use App\Modules\Learning\Application\Service\StudyCardAssembler;
use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

/**
 * Assembles a self-contained session: pick due-then-new terms (one global quota, scoped or
 * mixed), dedupe by term (a term in two collections counts once, at the input), hydrate content,
 * turn each into a playable card via the assembler, and persist the session with its fixed
 * composition so answers outside it can be rejected. Known terms with a due verification ride the
 * normal due selection and the assembler forces them to typing.
 *
 * A practice session takes a different pool entirely: every term in scope (ignoring `due_at`,
 * state and the daily quota), shuffled — free training on demand (device-batch F17). It still
 * writes reviews (flagged `is_practice`) but never schedules, so it drills whatever is there
 * without moving progress.
 */
final readonly class BuildStudySessionHandler
{
    private const MAX_SESSION_SIZE = 100;
    private const POOL_CAP = 500;

    public function __construct(
        private GetDueTermsHandler $dueTerms,
        private GetPracticeTermsHandler $practiceTerms,
        private LearnerProfileReader $profile,
        private IntroducedTermsReader $introduced,
        private TermContentReader $content,
        private UserCollectionTermsReader $collectionTerms,
        private StudyCardAssembler $assembler,
        private StudySessionRepository $sessions,
        private TransactionManager $tx,
        private Clock $clock,
        private EnabledModes $enabled,
    ) {}

    public function __invoke(BuildStudySession $command): SessionView
    {
        $now = $this->clock->now();
        $size = max(1, min(self::MAX_SESSION_SIZE, $command->sessionSize));

        // Practice draws the whole scope (all terms, any state, ignoring due_at) and never spends
        // the daily quota; a normal session takes due-then-new under the remaining new-term quota.
        $due = $this->dedupeByTerm($command->isPractice
            ? ($this->practiceTerms)(new GetPracticeTerms(
                userId: $command->actorId,
                sessionSize: $size,
                collectionId: $command->collectionId,
            ))
            : ($this->dueTerms)(new GetDueTerms(
                userId: $command->actorId,
                now: $now,
                sessionSize: $size,
                newTermsRemaining: min($size, max(0, $this->profile->newTermsPerDay($command->actorId) - $this->introduced->countForDay($command->actorId, $now))),
                collectionId: $command->collectionId,
            )));

        $termIds = array_map(static fn (DueTermView $v): TermId => $v->termId, $due);
        $content = $this->content->byIds($termIds);

        // Distractor pool: the scoped collection's terms, else the session's own term set.
        $poolIds = $command->collectionId !== null
            ? $this->collectionTerms->termIdsForCollection($command->actorId, $command->collectionId, self::POOL_CAP)
            : array_map(static fn (TermId $id): string => $id->value, $termIds);

        $cards = [];
        /** @var list<TermId> $composition */
        $composition = [];
        foreach ($due as $view) {
            $termContent = $content[$view->termId->value] ?? null;
            if ($termContent === null) {
                continue; // nothing to render without content
            }
            $cards[] = $this->assembler->assemble($command->actorId, $view, $termContent, $poolIds, $this->enabled);
            $composition[] = $view->termId;
        }

        $sessionId = $command->sessionId ?? StudySessionId::generate();
        $this->tx->run(function () use ($sessionId, $command, $composition, $now): void {
            $this->sessions->save(StudySession::start(
                id: $sessionId,
                userId: $command->actorId,
                isPractice: $command->isPractice,
                composition: $composition,
                startedAt: $now,
                collectionId: $command->collectionId !== null ? CollectionId::fromString($command->collectionId) : null,
            ));
        });

        return new SessionView($sessionId->value, $cards);
    }

    /**
     * @param  list<DueTermView>  $due
     * @return list<DueTermView>
     */
    private function dedupeByTerm(array $due): array
    {
        $seen = [];
        $out = [];
        foreach ($due as $view) {
            if (isset($seen[$view->termId->value])) {
                continue;
            }
            $seen[$view->termId->value] = true;
            $out[] = $view;
        }

        return $out;
    }
}
