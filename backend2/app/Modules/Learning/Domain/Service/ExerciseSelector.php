<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Learning\Domain\ValueObject\TermPlayability;

/**
 * Picks the exercise mode for a card.
 *
 * The rung comes first. {@see LearningLadder} says which step of the acquisition ladder the pair
 * stands on, and {@see ModeAdmission} says which trainers that rung admits at all — a pair met
 * five minutes ago cannot be dealt dictation however many modes are switched on. Only inside the
 * admitted set does the historic rotation still run, so the variety this class has always provided
 * survives, bounded:
 *
 *   step 0  intro                    the word is shown, not asked
 *   step 1  recognition forward      term → translation
 *   step 2  recognition reverse      translation → term
 *   step 3  assembly / choice        word_bank, cloze, scramble, pick_correct, multiple_choice,
 *                                    speaking (say the WORD)
 *   step 4  + typed production       typing, listening
 *   step 5  + dictation              …and speaking switches to reading the EXAMPLE aloud
 *   known   (outside the ladder)     always typing — recognition proves nothing here
 *
 * Within steps 3–5 the rotation is keyed on `reps` (never rand()), so it is deterministic and
 * testable and a retry does not jump modes. New rungs go on the END of a rotation list so enabling
 * one does not renumber the modes a word already partway through it has been getting.
 *
 * A ladder here is a plain ORDERED LIST, passed through three independent filters — is the mode
 * switched on ({@see EnabledModes}), can this term's data build it ({@see TermPlayability}), has
 * this pair earned it ({@see ModeAdmission}). Keeping them apart is what keeps this readable at
 * ten modes instead of collapsing into a tree of conditions.
 *
 * Everything degrades only within the enabled set, so a mode a user has switched off is never
 * handed out — with one deliberate exception, {@see floor()}.
 */
final class ExerciseSelector
{
    /**
     * The rung this card is actually dealt at — which is not always the rung the pair stands on.
     *
     * Two callers need the same answer and must not compute it twice: this class, to pick the mode,
     * and the card assembler, to decide the card's SHAPE (which direction the question asks in, and
     * whether its options are the session's neighbours or the enrichment distractors). One
     * derivation, so the two can never disagree about what the learner is being shown.
     *
     * @param  int|null  $override  the rung this card was laid out at, when a session gives a term
     *                             several cards at different rungs ({@see SessionLayout})
     * @return int|null  null = outside the ladder (a `known` verification)
     */
    public function effectiveStep(
        TermProgress $progress,
        EnabledModes $enabled,
        ModeAdmission $admission,
        ?int $override = null,
    ): ?int {
        if ($progress->state() === LearningState::Known) {
            return null;
        }

        $step = $override ?? $progress->ladderStep() ?? LearningLadder::STEP_ASSEMBLY;

        // Rung 0 with the intro trainer switched off: the pair simply starts one rung higher, at
        // recognition — exactly what happened before the ladder existed. The ladder still advances,
        // because a passed forward-recognition moves the pair on whether or not it saw an intro.
        if ($step === LearningLadder::STEP_INTRO
            && ! ($enabled->has(ExerciseMode::Intro) && $admission->allows(ExerciseMode::Intro, $step))) {
            return LearningLadder::STEP_RECOGNITION_FORWARD;
        }

        return $step;
    }

    public function select(
        TermProgress $progress,
        EnabledModes $enabled,
        TermPlayability $playable,
        ModeAdmission $admission,
        ?int $stepOverride = null,
    ): ExerciseMode {
        // `known` verification is always typing — recognition proves nothing here. Outside the
        // ladder entirely (effectiveStep is null), so the admission matrix does not apply.
        $step = $this->effectiveStep($progress, $enabled, $admission, $stepOverride);
        if ($step === null) {
            return $this->prefer(ExerciseMode::Typing, $enabled, $playable);
        }

        if ($step === LearningLadder::STEP_INTRO) {
            return ExerciseMode::Intro;
        }

        // Rungs 1–2: the two recognition directions. Both are multiple_choice; which direction the
        // card asks in is the rung's business, not the mode's, and is read off the step by the
        // card assembler. Options come from the session's neighbours, not from the enrichment
        // distractors — see StudyCardAssembler.
        if (LearningLadder::isRecognitionStep($step)) {
            return $this->prefer(ExerciseMode::MultipleChoice, $enabled, $playable);
        }

        // Rungs 3–5. `review` cycles on `reps % n`; the reps ≥ 1 learning/relearning branch leads
        // with its base mode (word_bank/typing) on `(reps-1) % n` so the meeting right after
        // graduation is the base one and later ones fan out.
        if ($progress->state() === LearningState::Review) {
            // `dictation` only here, and last: writing a whole sentence from hearing it is the most
            // demanding thing the app asks, so it belongs to terms already in review rather than to
            // one still being learned. New rungs go on the END so enabling one does not renumber
            // the rotation for words that are already partway through it.
            $ladder = [
                ExerciseMode::Typing,
                ExerciseMode::Listening,
                ExerciseMode::Cloze,
                ExerciseMode::Scramble,
                ExerciseMode::Dictation,
                ExerciseMode::PickCorrect,
                // On the END, like every rung added since: switching speaking on must not renumber
                // the rotation for words already partway through it.
                ExerciseMode::Speaking,
            ];
            $offset = $progress->reps();
        } else {
            $base = $playable->supports(ExerciseMode::WordBank) ? ExerciseMode::WordBank : ExerciseMode::Typing;
            // `pick_correct` joins the learning rung too: spotting a wrong sentence is recognition,
            // which a term still being learned can do — unlike dictation, which stays review-only.
            // `speaking` joins the learning rung too — saying a word you have just graduated on is
            // the same act the typed trainers ask for, only out loud, and unlike dictation it does
            // not need the pair to have been through the scheduler several times first.
            $ladder = [$base, ExerciseMode::Listening, ExerciseMode::Cloze, ExerciseMode::Scramble, ExerciseMode::PickCorrect, ExerciseMode::Speaking];
            $offset = max(0, $progress->reps() - 1);
        }

        $rotation = $enabled->only($playable->only($admission->only($ladder, $step)));
        if ($rotation !== []) {
            return $rotation[$offset % count($rotation)];
        }

        // The rotation is empty for this term — every rung is switched off, gated out by the term's
        // own data, or not yet admitted at this step. Fall back to ORDINARY multiple_choice (its own
        // options, from the enrichment distractors, not the ladder's far ones), which the matrix
        // admits from step 3 up and which fits every term. Falling back to typing here instead — as
        // this did before the ladder — would hand typed production to exactly the plainest terms
        // (one word, no example), which is the case step 4 exists to hold back.
        return $this->prefer(ExerciseMode::MultipleChoice, $enabled, $playable);
    }

    /**
     * Free-practice mode selection — NOT the SRS ladder. Practice fans across ALL enabled modes the
     * term's data supports ({@see TermPlayability}); the reps ladder is ignored entirely. Cards
     * round-robin over the applicable set by a per-card seed (card index + a per-term offset), so
     * one practice session shows every applicable mode and a repeat round re-deals different modes
     * to the same words.
     *
     * `intro` is excluded here and nowhere else needs to say so: free practice is a drill, and a
     * card that asks nothing is not one. It also would not advance the ladder — practice never
     * moves progress — so it would be a card that does nothing at all.
     *
     * NOTE: the acquisition ladder does NOT gate practice yet. This selection is mirrored in Dart
     * (the device builds its own practice sessions offline) and pinned by
     * tests/Fixtures/practice-mode-contract.json, so admitting the matrix here needs the client
     * port to land in the same change.
     *
     * @param  int  $rotation  card index + a stable per-term offset (drives the round-robin)
     */
    public function selectForPractice(EnabledModes $enabled, int $rotation, TermPlayability $playable): ExerciseMode
    {
        $applicable = $playable->only($this->drillable($enabled));
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
        return $playable->only($this->drillable($enabled)) !== [];
    }

    /**
     * The enabled modes that actually ASK the learner something. `intro` is switched on and off
     * like a trainer, but it is not one: it can never be an answer to "what can this term be
     * drilled in", so every question of that shape filters it out here, once.
     *
     * @return list<ExerciseMode>
     */
    private function drillable(EnabledModes $enabled): array
    {
        return array_values(array_filter(
            $enabled->modes,
            static fn (ExerciseMode $mode): bool => $mode->isGraded(),
        ));
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
        foreach ($this->drillable($enabled) as $mode) {
            if ($playable->supports($mode)) {
                return $mode; // unreachable while MC is enabled, kept so the floor is never worse
            }
        }

        return ExerciseMode::MultipleChoice;
    }
}
