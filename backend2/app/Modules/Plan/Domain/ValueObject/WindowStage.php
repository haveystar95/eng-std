<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * ONE STAGE ROW OF THE DAY WINDOW (кадры 23-0a…0c). The count, the minutes left and a share that is
 * neither empty nor full belong to the CURRENT stage only: a done stage is a full bar and the word
 * «пройдено», a locked one an empty bar and «впереди», and neither carries a number — so a number
 * cannot be printed on a row that has none.
 */
final readonly class WindowStage
{
    private function __construct(
        public Stage $stage,
        public StageState $state,
        public ?int $doneCount,
        public ?int $total,
        public ?int $minutesLeft,
        public float $share,
    ) {}

    public static function done(Stage $stage): self
    {
        return new self($stage, StageState::Done, null, null, null, 1.0);
    }

    public static function locked(Stage $stage): self
    {
        return new self($stage, StageState::Locked, null, null, null, 0.0);
    }

    /** @param int $minutesLeft the minutes the stage's unanswered cards take by the day's pace ({@see \App\Modules\Plan\Domain\Service\DayPace}) */
    public static function current(Stage $stage, int $answered, int $total, int $minutesLeft): self
    {
        $answered = max(0, min($answered, $total));

        return new self(
            $stage,
            StageState::Current,
            $answered,
            $total,
            max(0, $minutesLeft),
            $total > 0 ? round($answered / $total, 2) : 0.0,
        );
    }
}
