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
 *   reps ≥ 1 (learning/relearning, produced before) → production rotation, base first:
 *                                                     multi-word → word_bank, single word → typing,
 *                                                     then listening, then cloze (if example-backed)
 *   review                                         → production rotation: typing / listening / cloze
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
 * `listening` (hear it, type it) and `cloze` (fill the blank in the example) are production modes.
 * `cloze` is only offered when the term has an example that actually contains the answer — otherwise
 * there is nothing to blank, so it is dropped from the rotation (the slot degrades to typing/the
 * next enabled production mode). `known` is a verification check; recognition would just let the
 * user recognise it again, so it is always typing.
 *
 * Everything degrades only within the enabled set (config), so a mode that isn't switched on is
 * never handed out. New production steps slot into the ordered lists below — no state plumbing.
 */
final class ExerciseSelector
{
    /**
     * @param  int   $answerWordCount  whitespace-separated words in the target answer; word_bank
     *                                 needs at least two (nothing to assemble from one)
     * @param  bool  $clozeable        the term's example exists and contains the answer, so a blank
     *                                 can be cut from it; when false, cloze is never selected
     */
    public function select(
        TermProgress $progress,
        EnabledModes $enabled,
        int $answerWordCount = 2,
        bool $clozeable = false,
    ): ExerciseMode {
        // `known` verification is always typing — recognition proves nothing here.
        if ($progress->state() === LearningState::Known) {
            return $this->prefer(ExerciseMode::Typing, $enabled);
        }

        // First meeting (reps 0, new/learning/relearning) → recognition.
        if ($progress->state() !== LearningState::Review && $progress->reps() === 0) {
            return $this->prefer(ExerciseMode::MultipleChoice, $enabled);
        }

        // Production rotation. `review` cycles typing/listening/cloze on `reps % n`; the reps ≥ 1
        // learning/relearning branch leads with its base mode (word_bank/typing) on `(reps-1) % n`
        // so the second meeting is the base and later ones fan out to listening/cloze. Cloze is a
        // rotation step only when the example can be blanked — otherwise it is simply left out.
        if ($progress->state() === LearningState::Review) {
            $ladder = $clozeable
                ? [ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Cloze]
                : [ExerciseMode::Typing, ExerciseMode::Listening];
            $offset = $progress->reps();
        } else {
            $base = $answerWordCount >= 2 ? ExerciseMode::WordBank : ExerciseMode::Typing;
            $ladder = $clozeable
                ? [$base, ExerciseMode::Listening, ExerciseMode::Cloze]
                : [$base, ExerciseMode::Listening];
            $offset = max(0, $progress->reps() - 1);
        }

        $rotation = $enabled->only($ladder);
        if ($rotation !== []) {
            return $rotation[$offset % count($rotation)];
        }

        return $this->prefer(ExerciseMode::Typing, $enabled);
    }

    /**
     * Free-practice mode selection — NOT the SRS ladder. Practice fans across ALL enabled modes,
     * constrained only by the term's hard type limits (word_bank needs ≥ 2 words, cloze needs an
     * example to blank; multiple_choice/typing/listening fit any term). Cards round-robin over the
     * applicable set by a per-card seed (card index + a per-term offset), so one practice session
     * shows every applicable mode and a repeat round re-deals different modes to the same words.
     *
     * @param  int   $rotation   card index + a stable per-term offset (drives the round-robin)
     * @param  int   $answerWordCount  words in the answer (word_bank needs ≥ 2)
     * @param  bool  $clozeable  the example contains the answer (cloze needs it)
     */
    public function selectForPractice(EnabledModes $enabled, int $rotation, int $answerWordCount, bool $clozeable): ExerciseMode
    {
        $applicable = $this->applicableModes($enabled, $answerWordCount, $clozeable);
        if ($applicable === []) {
            return $enabled->first(); // typing/MC always apply, so this is only a safety net
        }

        $n = count($applicable);

        return $applicable[(($rotation % $n) + $n) % $n]; // guard against a negative seed
    }

    /**
     * The enabled modes a given term can actually be drilled in, in config order (a stable
     * round-robin). Only the hard type constraints filter here — practice ignores the reps ladder.
     *
     * @return list<ExerciseMode>
     */
    private function applicableModes(EnabledModes $enabled, int $answerWordCount, bool $clozeable): array
    {
        $out = [];
        foreach ($enabled->modes as $mode) {
            $fits = match ($mode) {
                ExerciseMode::WordBank => $answerWordCount >= 2, // nothing to assemble from one word
                ExerciseMode::Cloze => $clozeable,               // needs an example holding the answer
                default => true,                                 // multiple_choice / typing / listening
            };
            if ($fits) {
                $out[] = $mode;
            }
        }

        return $out;
    }

    private function prefer(ExerciseMode $mode, EnabledModes $enabled): ExerciseMode
    {
        return $enabled->has($mode) ? $mode : $enabled->first();
    }
}
