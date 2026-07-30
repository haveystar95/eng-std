<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\StudyCardView;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

/**
 * Composes the due-card selection (Learning) with term content (Vocabulary) into
 * self-contained cards — the client cannot make another call mid-session. New terms fill
 * leftover slots up to the day's remaining quota (daily cap minus terms already introduced).
 */
final readonly class GetStudyCardsHandler
{
    public function __construct(
        private GetDueTermsHandler $dueTerms,
        private TermContentReader $termContent,
        private IntroducedTermsReader $introduced,
        private LearnerProfileReader $learnerProfile,
    ) {}

    /** @return list<StudyCardView> */
    public function __invoke(GetStudyCards $query): array
    {
        // One global daily new-term quota, from the user's profile. A scoped session draws from
        // the SAME remaining quota — opening five collections must not grant five times the norm.
        $perDay = $this->learnerProfile->newTermsPerDay($query->userId);
        $remaining = max(0, $perDay - $this->introduced->countForDay($query->userId, $query->now));
        $newRemaining = min($query->sessionSize, $remaining);

        $due = ($this->dueTerms)(new GetDueTerms(
            userId: $query->userId,
            now: $query->now,
            sessionSize: $query->sessionSize,
            newTermsRemaining: $newRemaining,
            collectionId: $query->collectionId,
        ));

        $content = $this->termContent->byIds(array_map(static fn ($view) => $view->termId, $due));

        $cards = [];
        foreach ($due as $view) {
            $card = $content[$view->termId->value] ?? null;
            $cards[] = new StudyCardView(
                termId: $view->termId->value,
                state: $view->state->value,
                intervalDays: $view->intervalDays,
                dueAt: $view->dueAt,
                text: $card?->text,
                type: $card?->type,
                transcription: $card?->transcription,
                translation: $card?->translation,
                example: $card?->example,
                exampleTranslation: $card?->exampleTranslation,
            );
        }

        return $cards;
    }
}
