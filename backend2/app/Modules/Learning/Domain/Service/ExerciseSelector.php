<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;

/**
 * Picks the exercise mode for a card from its progress state — the escalation ladder is state,
 * so a skipped intro is a state, not a flag:
 *   new → multiple_choice, learning → word_bank,
 *   review → rotate typing / listening / cloze, relearning → back to multiple_choice.
 * A `known` card only reaches selection when its verification is due, and is always checked in
 * typing. Everything degrades within the enabled set.
 *
 * The review rotation is keyed on the term's own review counter, never rand(): deterministic,
 * so it is testable and a card doesn't jump modes on a retry.
 */
final class ExerciseSelector
{
    public function select(TermProgress $progress, EnabledModes $enabled): ExerciseMode
    {
        if ($progress->state() === LearningState::Review) {
            $rotation = $enabled->only([ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Cloze]);
            if ($rotation !== []) {
                return $rotation[$progress->reps() % count($rotation)];
            }
        }

        $preferred = match ($progress->state()) {
            LearningState::Known => ExerciseMode::Typing, // verification: recognition proves nothing
            LearningState::New, LearningState::Relearning => ExerciseMode::MultipleChoice,
            LearningState::Learning => ExerciseMode::WordBank,
            LearningState::Review => ExerciseMode::Typing,
        };

        return $enabled->has($preferred) ? $preferred : $enabled->first();
    }
}
