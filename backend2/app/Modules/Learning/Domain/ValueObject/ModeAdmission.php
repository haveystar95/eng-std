<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Service\LearningLadder;

/**
 * The ADMISSION MATRIX: which trainers a pair is allowed to be shown in at a given rung of the
 * acquisition ladder, and where their options come from. Three questions are kept apart on
 * purpose, and this is the third:
 *
 *   EnabledModes     is this trainer switched on for this user?      (settings)
 *   TermPlayability  can this trainer be built for this term's data? (content)
 *   ModeAdmission    has this PAIR earned this trainer yet?          (ladder)
 *
 * Each is a filter over an ordered list, so the selector stays a ladder plus three `only()` calls
 * instead of a tree of conditions. Adding a mode adds one row here and nothing else.
 *
 * The rows are DATA (`learning_mode_settings`, alongside the on/off toggle and per-user override),
 * not constants: what a mode asks of the learner is a product judgement that should move with
 * evidence and with one admin action, not with a deploy.
 */
final readonly class ModeAdmission
{
    /** @var array<string, ModeRule> keyed by ExerciseMode value */
    private array $rules;

    /** @param array<string, ModeRule> $rules mode value => rule */
    public function __construct(array $rules)
    {
        $kept = [];
        foreach ($rules as $mode => $rule) {
            if (ExerciseMode::tryFrom((string) $mode) !== null) {
                $kept[(string) $mode] = $rule;
            }
        }

        $this->rules = $kept;
    }

    /**
     * Is this mode admitted at this rung?
     *
     * A mode with no rule is NOT admitted anywhere. Fail-closed, deliberately: a new trainer whose
     * row nobody has written should be undealable until it is written, not universally available —
     * the latter is how a dictation card reaches a word the learner met a minute ago.
     *
     * A mode that produces no grade (`intro`) is admitted at its rung and NOWHERE ABOVE it. That
     * ceiling is a Domain fact rather than a stored one: an intro after the learner has already
     * answered the word is not a gentler card, it is a wasted one — no policy would want it.
     */
    public function allows(ExerciseMode $mode, int $step): bool
    {
        $rule = $this->rules[$mode->value] ?? null;
        if ($rule === null) {
            return false;
        }

        $min = $rule->minStep();

        return $mode->isGraded() ? $step >= $min : $step === $min;
    }

    /**
     * Where this card's wrong options come from. `distant` is a ladder concession — it exists to
     * make a first meeting winnable — so a pair that is off the ladder always gets `standard`,
     * whatever is stored.
     */
    public function optionsPolicyFor(ExerciseMode $mode, Acquisition $acquisition): OptionsPolicy
    {
        if ($acquisition === Acquisition::Graduated) {
            return OptionsPolicy::Standard;
        }

        return isset($this->rules[$mode->value])
            ? $this->rules[$mode->value]->optionsPolicy
            : OptionsPolicy::Standard;
    }

    public function ruleFor(ExerciseMode $mode): ?ModeRule
    {
        return $this->rules[$mode->value] ?? null;
    }

    /**
     * The given modes admitted at this rung, order preserved — the third filter a ladder passes
     * through, alongside {@see EnabledModes::only()} and {@see TermPlayability::only()}.
     *
     * @param  list<ExerciseMode>  $modes
     * @return list<ExerciseMode>
     */
    public function only(array $modes, int $step): array
    {
        return array_values(array_filter($modes, fn (ExerciseMode $mode): bool => $this->allows($mode, $step)));
    }

    /**
     * THE RUNG-0 CORNER OF FREE PRACTICE — what a pair with no rung of its own may be drilled in.
     *
     * The canon (владелец/архитектор, BUGFIX-2 Ч.2б): «свободная практика ступени 0 = рецептивные
     * режимы; продуктивные (письмо по памяти, диктант) открываются лестницей». So the corner is
     * узнавание/выбор, сборка, произнеси, аудио «услышал→напиши», и description_match при наличии
     * описания — everything except the two trainers {@see ExerciseMode::openedByLadderOnly()} names.
     *
     * Which is why this is not `only($modes, STEP_UNENROLLED_PRACTICE)`, the one line it replaces:
     * the matrix opens `listening` at the typed-production rung, because in a PLANNED session it
     * follows typing. Free practice is not a session — it schedules nothing, advances nothing and
     * spends nothing — so its corner is drawn by what a card ASKS, not by what a rung has earned,
     * and hearing a word and writing it down asks no more of a first meeting than tapping it does.
     * Reading the rung straight off the matrix silently cost every rung-0 word its audio trainer.
     *
     * The matrix still has the last word in both directions: a mode with no rule is admitted
     * nowhere (fail-closed, as everywhere else here), and a productive trainer is asked about at
     * exactly the rung such a word is drilled at, so the admin can still close one. What the panel
     * cannot do through this is push a receptive trainer out of the corner by raising its rung —
     * that rung is about the ladder, and this corner is not on it.
     *
     * @param  list<ExerciseMode>  $modes
     * @return list<ExerciseMode>
     */
    public function onlyPracticeCorner(array $modes): array
    {
        return array_values(array_filter($modes, fn (ExerciseMode $mode): bool => $this->allows(
            $mode,
            // A receptive trainer is asked about at the TOP rung: «is it on the ladder at all»,
            // never «has this word climbed to it».
            $mode->openedByLadderOnly() ? LearningLadder::STEP_UNENROLLED_PRACTICE : LearningLadder::STEP_DICTATION,
        )));
    }

    /**
     * The matrix as the wire sees it — the client mirrors it to build sessions offline, and the
     * admin API reads it. Ordered by rung then mode so the payload is stable between requests.
     *
     * @return list<array{mode: string, min_acquisition: string, min_learning_step: int|null, min_reps: int|null, options_policy: string, min_step: int}>
     */
    public function toWire(): array
    {
        $rows = [];
        foreach ($this->rules as $mode => $rule) {
            $rows[] = [
                'mode' => $mode,
                'min_acquisition' => $rule->minAcquisition->value,
                'min_learning_step' => $rule->minLearningStep,
                // RENAME-DEFERRED: the DB column is `min_successful_reviews` (honest name, since
                // QA-20's «честная лестница» наряд), but the /sync wire key stays `min_reps` — the
                // Flutter client is out of scope here and reads this key today. Move both together
                // at the next contract version bump (FSRS), not silently in one backend change.
                'min_reps' => $rule->minSuccessfulReviews,
                'options_policy' => $rule->optionsPolicy->value,
                // Derived, and sent as well as the three thresholds: the client needs the rung to
                // filter a ladder, and deriving it there is one more place the two runtimes could
                // disagree. {@see LearningLadder} stays the single definition, on this side.
                'min_step' => $rule->minStep(),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['min_step'], $a['mode']] <=> [$b['min_step'], $b['mode']]);

        return $rows;
    }

    /** The shipped matrix, used to seed the table and as the emergency fallback if it is emptied. */
    public static function shipped(): self
    {
        return new self([
            ExerciseMode::Intro->value => new ModeRule(Acquisition::New),
            ExerciseMode::MultipleChoice->value => new ModeRule(
                Acquisition::Learning,
                minLearningStep: LearningLadder::STEP_RECOGNITION_FORWARD,
                optionsPolicy: OptionsPolicy::Distant,
            ),
            ExerciseMode::WordBank->value => new ModeRule(Acquisition::Graduated),
            ExerciseMode::Cloze->value => new ModeRule(Acquisition::Graduated),
            ExerciseMode::PickCorrect->value => new ModeRule(Acquisition::Graduated),
            ExerciseMode::Scramble->value => new ModeRule(Acquisition::Graduated),
            ExerciseMode::Typing->value => new ModeRule(Acquisition::Graduated, minSuccessfulReviews: LearningLadder::TYPING_MIN_SUCCESSES),
            // Typed production with the prompt taken away — the same act as typing, strictly
            // harder — so it cannot be admitted earlier than typing is.
            ExerciseMode::Listening->value => new ModeRule(Acquisition::Graduated, minSuccessfulReviews: LearningLadder::TYPING_MIN_SUCCESSES),
            ExerciseMode::Dictation->value => new ModeRule(Acquisition::Graduated, minSuccessfulReviews: LearningLadder::DICTATION_MIN_SUCCESSES),
            // Speaking opens on graduation, with the assembly trainers — its own two forms then
            // separate themselves by rung inside the mode ({@see ExerciseMode::gradesAgainstExample}),
            // which is why there is ONE row here and not two. A row per form would put the same
            // trainer in the registry twice and give the owner two toggles for one thing to switch on.
            ExerciseMode::Speaking->value => new ModeRule(Acquisition::Graduated),
            // `description_match` opens on graduation too — its passport floor, beside the other
            // trainers that assume a stable pair. `standard` options and not `distant`: `distant`
            // exists for the recognition rungs, where a near-miss at a first meeting is a spelling
            // test; this card's options come from the distractor reader, which already refuses a
            // candidate whose translations overlap the target's — the failure that actually matters
            // when the prompt is a description rather than a gloss.
            ExerciseMode::DescriptionMatch->value => new ModeRule(Acquisition::Graduated),
        ]);
    }
}
