<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;

/**
 * Picks the exercise mode for a card. The ladder is a function of how many times the term has
 * actually been answered (`reps`) and the shape of its answer, not of state alone — variety kicks
 * in from the second meeting (Training Loop v2, device-batch F-ladder):
 *
 *   reps 0 (first meeting, incl. a skipped intro)  → recognition: multiple_choice
 *   reps ≥ 1 (produced before)                     → production: multi-word → word_bank,
 *                                                     single word → typing
 *   review                                         → rotate typing / listening / cloze (by reps)
 *   known (verification due)                       → always typing
 *
 * Rationale: `reps` is the honest signal of familiarity. A brand-new term (no progress row → reps
 * 0) is recognised first; once the user has produced it at least once it graduates to production,
 * and single words go straight to typing (a word_bank of one tile is busywork, not recall). The
 * escalation is a *reps* fact, not a flag — a triaged-«unsure» term skips the intro because it
 * already carries reps, not because of a special case.
 *
 * `review` keeps its own deterministic production rotation (typing/listening/cloze, keyed on the
 * review counter so a retry doesn't jump modes); `known` is a verification check, and recognition
 * would just let the user recognise it again and prove nothing, so it is always typing.
 *
 * Everything degrades only within the enabled set, so a mode that isn't switched on (listening,
 * cloze) is never handed out. New production steps slot in by extending the rotation set or the
 * `reps ≥ 1` branch — no state plumbing needed.
 */
final class ExerciseSelector
{
    /**
     * @param  int  $answerWordCount  whitespace-separated words in the target answer; word_bank
     *                                needs at least two (there is nothing to assemble from one)
     */
    public function select(TermProgress $progress, EnabledModes $enabled, int $answerWordCount = 2): ExerciseMode
    {
        // `review` keeps its production rotation across typing/listening/cloze, deterministic on reps.
        if ($progress->state() === LearningState::Review) {
            $rotation = $enabled->only([ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Cloze]);
            if ($rotation !== []) {
                return $rotation[$progress->reps() % count($rotation)];
            }

            return $this->prefer(ExerciseMode::Typing, $enabled);
        }

        // `known` verification is always typing — recognition proves nothing here.
        if ($progress->state() === LearningState::Known) {
            return $this->prefer(ExerciseMode::Typing, $enabled);
        }

        // new / learning / relearning: the recognition → production ladder, keyed on reps.
        $preferred = $progress->reps() === 0
            ? ExerciseMode::MultipleChoice                                          // first meeting: recognise
            : ($answerWordCount >= 2 ? ExerciseMode::WordBank : ExerciseMode::Typing); // then produce

        return $this->prefer($preferred, $enabled);
    }

    private function prefer(ExerciseMode $mode, EnabledModes $enabled): ExerciseMode
    {
        return $enabled->has($mode) ? $mode : $enabled->first();
    }
}
