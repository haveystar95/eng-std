<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\StudyCardView;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
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
    ) {}

    /** @return list<StudyCardView> */
    public function __invoke(GetStudyCards $query): array
    {
        $newRemaining = max(0, $query->newTermsPerDay - $this->introduced->countForDay($query->userId, $query->now));

        $due = ($this->dueTerms)(new GetDueTerms(
            userId: $query->userId,
            now: $query->now,
            sessionSize: $query->sessionSize,
            newTermsRemaining: $newRemaining,
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
