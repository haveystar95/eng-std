<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Start a plan as a DRAFT: the goal and the calendar, nothing generated, nothing enrolled. */
final readonly class CreatePlan
{
    public function __construct(
        public UserId $actorId,
        public string $goalText,
        public string $targetLang,
        public string $level,
        /** Y-m-d in the learner's own timezone, or NULL — «Без даты» (кадр V4·04б). */
        public ?string $eventDate,
        public int $minutesPerDay,
        /**
         * What the entry's listening step learned, straight off the screen that asked.
         *
         * Rows of `{text, translation, place, understood}`, or an empty list when the step was
         * skipped or never offered. The VERDICT is not here and never travels on the wire: it is
         * derived from the taps by {@see \App\Modules\Learning\Domain\ValueObject\ListeningDiagnostics::emphasis()},
         * so a client cannot send a balance that disagrees with the answers under it.
         *
         * @var list<array{text: string, translation: string, place: string, understood: bool}>
         */
        public array $listened = [],
    ) {}
}
