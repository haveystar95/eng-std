<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StagePassage;

/**
 * THE SIXTH STAGE IS WALKED WHEN A TALK COMES TO AN END OF ITS OWN (наряд CONV-2, п. 2) — written here, inside the
 * transaction that writes the talk, by both doors a talk can end through (the opening line and a move).
 *
 * The first such talk of the day is the one that walked it: the journal keeps one row per day and stage and never
 * moves it, so «Ещё раз» after it is a replay — a talk on top of a walked day, not a new attempt at the stage. A talk
 * closed by «Ещё раз» itself ended nothing and walks nothing ({@see Conversation::passesStage()}).
 */
final readonly class ConversationPassing
{
    public function __construct(private StagePassageRepository $passages) {}

    /** Call inside the transaction that saves the talk: the talk and the fact it walked the stage land together. */
    public function mark(Conversation $talk): void
    {
        $endedAt = $talk->endedAt();
        if (! $talk->passesStage() || $endedAt === null) {
            return;
        }
        $this->passages->record(new StagePassage($talk->planId(), $talk->dayId(), Stage::Conversation, $talk->id(), $endedAt));
    }
}
