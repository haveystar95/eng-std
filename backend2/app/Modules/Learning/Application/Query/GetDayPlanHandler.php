<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\DayPlanEntryView;
use App\Modules\Learning\Application\Dto\DayPlanView;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\ChipShuffler;
use App\Modules\Learning\Domain\Service\ExerciseSelector;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Dry-run of a user's study day: reuse the real scheduler (GetDueTerms) to pick which terms are due
 * on the target calendar date and how many new ones the quota would introduce, then run the real
 * ExerciseSelector on each to say which trainer it would arrive as — the same two pieces the live
 * session is built from ({@see \App\Modules\Learning\Application\Command\BuildStudySessionHandler}),
 * minus every write. Nothing is persisted: no StudySession, no progress, no reviews. This is the
 * truth of the planner for the admin day-simulator, not a SQL reconstruction of it.
 *
 * The day boundary follows the same calendar-day rule the scheduler uses (device-batch F19): the
 * plan for date D includes everything due through the end of D in the user's timezone.
 */
final readonly class GetDayPlanHandler
{
    private const MAX_SESSION_SIZE = 100;

    public function __construct(
        private GetDueTermsHandler $dueTerms,
        private LearnerProfileReader $profile,
        private IntroducedTermsReader $introduced,
        private TermContentReader $content,
        private ExerciseSelector $selector,
        private ChipShuffler $chips,
        private EnabledModes $enabled,
        private Clock $clock,
    ) {}

    public function __invoke(GetDayPlan $query): DayPlanView
    {
        $size = max(1, min(self::MAX_SESSION_SIZE, $query->sessionSize));
        $tz = $this->profile->timezoneFor($query->userId);

        $dayStart = ($query->date !== null && $query->date !== ''
            ? new DateTimeImmutable($query->date, $tz)
            : $this->clock->now()->setTimezone($tz))->setTime(0, 0, 0);
        // Inclusive end-of-day: everything due on/before date D in the user's zone.
        $dayEnd = $dayStart->modify('+1 day')->modify('-1 second');

        $newPerDay = $this->profile->newTermsPerDay($query->userId);
        $introducedOnDay = $this->introduced->countForDay($query->userId, $dayEnd);
        $newRemaining = min($size, max(0, $newPerDay - $introducedOnDay));

        $due = ($this->dueTerms)(new GetDueTerms(
            userId: $query->userId,
            now: $dayEnd,
            sessionSize: $size,
            newTermsRemaining: $newRemaining,
        ));

        $termIds = array_map(static fn (DueTermView $v): TermId => $v->termId, $due);
        $content = $this->content->byIds($termIds);

        $entries = [];
        $dueCount = 0;
        $newIntroduced = 0;
        foreach ($due as $view) {
            $termContent = $content[$view->termId->value] ?? null;
            if ($termContent === null) {
                continue; // nothing renderable without content — the live session skips it too
            }

            $answer = $termContent->text;
            $clozeable = $termContent->example !== null
                && $termContent->example !== ''
                && mb_stripos($termContent->example, $answer) !== false;
            $wordCount = $this->chips->wordCount($answer);

            $progress = TermProgress::reconstitute(
                $query->userId, $view->termId, $view->state, TermProgress::DEFAULT_EASE,
                $view->intervalDays, $view->dueAt, $view->reps, 0, null,
            );
            $mode = $this->selector->select($progress, $this->enabled, $wordCount, $clozeable);

            $isNew = $view->state === LearningState::New;
            $isNew ? $newIntroduced++ : $dueCount++;

            $entries[] = new DayPlanEntryView(
                termId: $view->termId->value,
                text: $answer,
                translation: $termContent->translation,
                type: $termContent->type,
                state: $view->state->value,
                reps: $view->reps,
                intervalDays: $view->intervalDays,
                dueAt: $view->dueAt?->format(DateTimeInterface::ATOM),
                exerciseMode: $mode->value,
                clozeable: $clozeable,
                isNew: $isNew,
            );
        }

        return new DayPlanView(
            date: $dayStart->format('Y-m-d'),
            timezone: $tz->getName(),
            dueCount: $dueCount,
            newIntroduced: $newIntroduced,
            newTermsPerDay: $newPerDay,
            entries: $entries,
        );
    }
}
