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
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\ModeAdmissionReader;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\Service\SessionLayout;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\SessionSlot;
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
        private EnabledModesReader $enabledModes,
        private ModeAdmissionReader $admission,
        private SessionLayout $layout,
    ) {}

    public function __invoke(BuildStudySession $command): SessionView
    {
        $now = $this->clock->now();
        $size = max(1, min(self::MAX_SESSION_SIZE, $command->sessionSize));
        // Which trainers this learner has switched on — their override, or the product default.
        // Read once per session so every card in it is dealt from the same set.
        $enabled = $this->enabledModes->forUser($command->actorId);
        // …and which of them each rung of the acquisition ladder admits. Also read once: a card
        // dealt under one matrix and graded under another is a card nobody can explain.
        $matrix = $this->admission->matrixFor($command->actorId);

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
        $content = $this->content->byIds(
            $termIds,
            $this->profile->nativeLangFor($command->actorId),
        );

        // Distractor pool: the scoped collection's terms, else the session's own term set.
        $poolIds = $command->collectionId !== null
            ? $this->collectionTerms->termIdsForCollection($command->actorId, $command->collectionId, self::POOL_CAP)
            : array_map(static fn (TermId $id): string => $id->value, $termIds);

        // Terms we can actually render, in selection order. Everything below works off this list,
        // so a term whose content never arrived is out of the session entirely rather than out of
        // only some of its cards.
        $renderable = array_values(array_filter(
            $due,
            static fn (DueTermView $v): bool => isset($content[$v->termId->value]),
        ));

        // The far-option pool for the recognition rungs: this session's own terms. Built once,
        // handed to every card — an option that is another card in the same sitting is a word the
        // learner has a reason to have in mind, which is what makes a far option fair rather than
        // arbitrary.
        $neighbours = array_map(
            static fn (DueTermView $v): array => [
                'term_id' => $v->termId->value,
                'text' => $content[$v->termId->value]->text,
                'translation' => $content[$v->termId->value]->translation,
            ],
            $renderable,
        );

        // Practice keeps its flat running order (one card per term, already shuffled). The ladder
        // does not apply: practice schedules nothing and advances nothing, so a word cannot be
        // introduced by it.
        $slots = $command->isPractice
            ? array_map(static fn (DueTermView $v): SessionSlot => new SessionSlot($v->termId->value), $renderable)
            : $this->arrange(
                $renderable,
                $size,
                // Whether rung 0 exists at all for this learner. With the intro trainer switched
                // off a first meeting starts at recognition, so its chain is TWO cards, not three —
                // the layout has to be told, or it would reserve a slot for a card nobody deals and
                // the pair would be asked its forward recognition twice.
                introEnabled: $enabled->has(ExerciseMode::Intro) && $matrix->allows(ExerciseMode::Intro, LearningLadder::STEP_INTRO),
            );

        $byTerm = [];
        foreach ($renderable as $view) {
            $byTerm[$view->termId->value] = $view;
        }

        $cards = [];
        /** @var list<TermId> $composition */
        $composition = [];
        $seen = [];
        foreach ($slots as $cardIndex => $slot) {
            $view = $byTerm[$slot->termId];
            $cards[] = $this->assembler->assemble(
                $command->actorId, $view, $content[$slot->termId], $poolIds, $enabled, $matrix,
                isPractice: $command->isPractice, cardIndex: $cardIndex,
                slotStep: $slot->ladderStep, neighbours: $neighbours,
            );
            // The composition is a SET of terms, not of cards: a term now legitimately occupies
            // several slots, and what the composition guards is «was this term part of the session
            // at all», which is one fact however many times it was asked.
            if (! isset($seen[$slot->termId])) {
                $seen[$slot->termId] = true;
                $composition[] = $view->termId;
            }
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
     * Hand the selection to the layout, split by what each term costs in slots.
     *
     * A term on the acquisition ladder brings a CHAIN of cards — intro, then the two recognitions —
     * that must all land in this session; a graduated term brings one. The layout decides where
     * they go and defers, whole, any term whose chain does not fit.
     *
     * @param  list<DueTermView>  $renderable
     * @return list<SessionSlot>
     */
    private function arrange(array $renderable, int $size, bool $introEnabled): array
    {
        $ladder = [];
        $repeats = [];
        foreach ($renderable as $view) {
            if ($view->acquisition === Acquisition::Graduated) {
                $repeats[] = $view->termId->value;

                continue;
            }
            // Rung 0 for a pair never shown (rung 1 when the intro trainer is off); its stored step
            // for one already partway up. The layout only needs where the chain STARTS — it walks
            // the rest itself.
            $step = $view->acquisition === Acquisition::New
                ? ($introEnabled ? LearningLadder::STEP_INTRO : LearningLadder::STEP_RECOGNITION_FORWARD)
                : max(LearningLadder::STEP_RECOGNITION_FORWARD, min(LearningLadder::STEP_RECOGNITION_REVERSE, $view->learningStep));

            $ladder[] = ['term_id' => $view->termId->value, 'step' => $step];
        }

        return $this->layout->arrange($ladder, $repeats, $size);
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
