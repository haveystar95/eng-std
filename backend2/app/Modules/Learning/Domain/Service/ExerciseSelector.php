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
 * Everything degrades only within the enabled set, so a mode a user has switched off is never
 * handed out — with one deliberate exception, {@see floor()}.
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
            return $this->prefer(ExerciseMode::Typing, $enabled, $playable);
        }

        // First meeting (reps 0, new/learning/relearning) → recognition.
        if ($progress->state() !== LearningState::Review && $progress->reps() === 0) {
            return $this->prefer(ExerciseMode::MultipleChoice, $enabled, $playable);
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

        // The ladder is empty for this term — every rung is either switched off or gated out by the
        // term's own data.
        return $this->prefer(ExerciseMode::Typing, $enabled, $playable);
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
            return $this->floor($enabled, $playable);
        }

        $n = count($applicable);

        return $applicable[(($rotation % $n) + $n) % $n]; // guard against a negative seed
    }

    /**
     * Can this term be drilled at all under these toggles? False means every enabled mode is gated
     * out by the term's data, and the card the learner gets is the {@see floor()} — worth shouting
     * about, which is the caller's job (Domain does not log).
     */
    public function hasApplicableMode(EnabledModes $enabled, TermPlayability $playable): bool
    {
        return $playable->only($enabled->modes) !== [];
    }

    /** The mode this rung wants, if it is switched on and the term supports it; else the floor. */
    private function prefer(ExerciseMode $mode, EnabledModes $enabled, TermPlayability $playable): ExerciseMode
    {
        return $enabled->has($mode) && $playable->supports($mode)
            ? $mode
            : $this->floor($enabled, $playable);
    }

    /**
     * The last resort: multiple_choice, even when it is switched off.
     *
     * Toggles are per-user data now, so "no mode applies" is reachable by configuration — switch
     * off everything but word_bank and cloze, and a single-word term with no example has nowhere to
     * go. A card must still be playable, and multiple_choice is the only mode that fits every term
     * (it asks for the term itself and builds its options from other terms). Choosing to honour the
     * toggles here instead would mean an empty session, which is a worse answer to a misconfigured
     * toggle than an unexpected exercise.
     */
    private function floor(EnabledModes $enabled, TermPlayability $playable): ExerciseMode
    {
        foreach ($enabled->modes as $mode) {
            if ($playable->supports($mode)) {
                return $mode; // unreachable while MC is enabled, kept so the floor is never worse
            }
        }

        return ExerciseMode::MultipleChoice;
    }
}
