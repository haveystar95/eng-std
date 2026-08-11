<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\TermPlayability;

/**
 * Picks the exercise mode for a card. The ladder is a function of how many times the term has
 * actually been answered (`reps`) and the shape of its answer, not of state alone — variety kicks
 * in from the second meeting (Training Loop v2, device-batch F-ladder):
 *
 *   reps 0 (first meeting, incl. a skipped intro)  → recognition: multiple_choice
 *   reps ≥ 1 (learning/relearning, produced before) → production rotation, base first:
 *                                                     multi-word → word_bank, single word → typing,
 *                                                     then listening, cloze, scramble
 *   review                                         → rotation: typing / listening / cloze / scramble
 *   known (verification due)                       → always typing
 *
 * Rationale: `reps` is the honest signal of familiarity. A brand-new term (no progress row → reps
 * 0) is recognised first; once produced it graduates to a *rotating* production ladder so the same
 * word isn't drilled the same way twice running. The rotation is keyed on the review counter (never
 * rand()), so it's deterministic and testable and a retry doesn't jump modes.
 *
 * The reps ≥ 1 (learning/relearning) rotation is offset so the SECOND meeting always lands on the
 * base mode (word_bank/typing) — the recognition→production step from Training Loop v2 — and only
 * later meetings reach listening/cloze. The `review` rotation keeps its historic `reps % n` phase.
 *
 * A ladder here is a plain ORDERED LIST. Whether a term can actually be drilled in a given mode is
 * not this class's business: {@see TermPlayability} answers that, and a ladder is simply filtered
 * through it (`cloze` drops out for a term with no usable example, and the slot degrades to the
 * next enabled production mode). That split is what keeps the ladder readable as more modes land —
 * and what will let the list itself come from a stored policy later without touching this logic.
 *
 * Everything degrades only within the enabled set (config), so a mode that isn't switched on is
 * never handed out.
 */
final class ExerciseSelector
{
    public function select(
        TermProgress $progress,
        EnabledModes $enabled,
        TermPlayability $playable,
    ): ExerciseMode {
        // `known` verification is always typing — recognition proves nothing here.
        if ($progress->state() === LearningState::Known) {
            return $this->prefer(ExerciseMode::Typing, $enabled);
        }

        // First meeting (reps 0, new/learning/relearning) → recognition.
        if ($progress->state() !== LearningState::Review && $progress->reps() === 0) {
            return $this->prefer(ExerciseMode::MultipleChoice, $enabled);
        }

        // Production rotation. `review` cycles on `reps % n`; the reps ≥ 1 learning/relearning
        // branch leads with its base mode (word_bank/typing) on `(reps-1) % n` so the second
        // meeting is the base and later ones fan out.
        if ($progress->state() === LearningState::Review) {
            $ladder = [ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Cloze, ExerciseMode::Scramble];
            $offset = $progress->reps();
        } else {
            $base = $playable->supports(ExerciseMode::WordBank) ? ExerciseMode::WordBank : ExerciseMode::Typing;
            $ladder = [$base, ExerciseMode::Listening, ExerciseMode::Cloze, ExerciseMode::Scramble];
            $offset = max(0, $progress->reps() - 1);
        }

        $rotation = $enabled->only($playable->only($ladder));
        if ($rotation !== []) {
            return $rotation[$offset % count($rotation)];
        }

        return $this->prefer(ExerciseMode::Typing, $enabled);
    }

    /**
     * Free-practice mode selection — NOT the SRS ladder. Practice fans across ALL enabled modes the
     * term's data supports ({@see TermPlayability}); the reps ladder is ignored entirely. Cards
     * round-robin over the applicable set by a per-card seed (card index + a per-term offset), so
     * one practice session shows every applicable mode and a repeat round re-deals different modes
     * to the same words.
     *
     * @param  int  $rotation  card index + a stable per-term offset (drives the round-robin)
     */
    public function selectForPractice(EnabledModes $enabled, int $rotation, TermPlayability $playable): ExerciseMode
    {
        $applicable = $playable->only($enabled->modes);
        if ($applicable === []) {
            return $enabled->first(); // typing/MC always apply, so this is only a safety net
        }

        $n = count($applicable);

        return $applicable[(($rotation % $n) + $n) % $n]; // guard against a negative seed
    }

    private function prefer(ExerciseMode $mode, EnabledModes $enabled): ExerciseMode
    {
        return $enabled->has($mode) ? $mode : $enabled->first();
    }
}
