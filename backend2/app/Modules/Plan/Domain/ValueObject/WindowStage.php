<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * ONE STAGE ROW OF THE DAY WINDOW (кадры 23-0a…0c). The count, the minutes left and a share that is
 * neither empty nor full belong to the CURRENT stage only: a done stage is a full bar and the word
 * «пройдено», a locked one an empty bar and «впереди», and neither carries a number — so a number
 * cannot be printed on a row that has none.
 *
 * The TALK's row carries two more things, in every state (наряд CONV-2, п. 12): the title of its entry
 * — «Поговори с врачом», inflected by the server (кадр 37-5) — and how many scenes the talk walks
 * («Разговор целиком · 3 сцены» on the rehearsal). Card rows have neither.
 *
 * EVERY row carries its PLANNED minutes (`minutes`, наряд BACK-TAILS-2 §4) in every state — «около 4 минут» on the rows
 * of a day not opened yet (кадры 37-1, 37-2): what the whole stage is reckoned to take, not what is left of it.
 *
 * And whether the stage may be walked AGAIN (`again`, наряд FIX-3 §8) — «Ещё раз» of its row: a stage of cards always
 * may, on a closed day too (the phone walks its cards once more by itself); the talk — once walked, while the day's
 * replays of the learner's day last. A stage of cards carries its summary as well (`summary`, §10, кадр 30-6).
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
        public ?string $talkTitle = null,
        public ?int $scenes = null,
        public int $minutes = 0,
        public bool $again = false,
        public ?StageSummary $summary = null,
    ) {}

    public static function done(Stage $stage): self
    {
        return new self($stage, StageState::Done, null, null, null, 1.0);
    }

    public static function locked(Stage $stage): self
    {
        return new self($stage, StageState::Locked, null, null, null, 0.0);
    }

    /**
     * THE STAGE BEING WALKED THAT HAS NO CARDS (наряд CONV-1) — the talk with the agent. It carries
     * its minutes and no count at all: «Разговор с врачом · идёт · около 6 минут» (кадр 37-1). A
     * «0 / 1» there would be a number invented to fill a column.
     */
    public static function talking(Stage $stage, int $minutesLeft): self
    {
        return new self($stage, StageState::Current, null, null, max(0, $minutesLeft), 0.0);
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

    /** The talk's row with its entry title and the number of scenes it walks (наряд CONV-2, п. 12). */
    public function withTalk(?string $title, int $scenes): self
    {
        return new self($this->stage, $this->state, $this->doneCount, $this->total, $this->minutesLeft, $this->share, $title, max(0, $scenes), $this->minutes, $this->again, $this->summary);
    }

    /** The row with the minutes its whole stage is reckoned to take (наряд BACK-TAILS-2 §4). */
    public function planned(int $minutes): self
    {
        return new self($this->stage, $this->state, $this->doneCount, $this->total, $this->minutesLeft, $this->share, $this->talkTitle, $this->scenes, max(0, $minutes), $this->again, $this->summary);
    }

    /** The row with whether its stage may be walked again, and — for a stage of cards — its summary (наряд FIX-3 §8, §10). */
    public function walkable(bool $again, ?StageSummary $summary = null): self
    {
        return new self($this->stage, $this->state, $this->doneCount, $this->total, $this->minutesLeft, $this->share, $this->talkTitle, $this->scenes, $this->minutes, $again, $summary);
    }
}
