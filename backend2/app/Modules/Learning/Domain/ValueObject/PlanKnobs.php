<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * THE SIX KNOBS a plan turns for a learner's level — configuration, never trainer code.
 *
 * The level moves the DIFFICULTY of a card and never the topic ({@see PlanLevel}): a `zero` learner
 * going to the doctor still goes to the doctor, but they get three options instead of four, distant
 * distractors instead of near ones, and the first letter of the word they are asked to type. That
 * is the whole mechanism — no separate trainers for beginners, no second ladder, one set of numbers
 * per level.
 *
 * ## Set even where nothing reads them, on purpose
 *
 * Some of these reach a trainer that already understands them and some do not, and BOTH are stored.
 * A knob nobody reads yet is a stated intention with a place to live; a knob invented later has to
 * be threaded through a migration, a reader, a seed and four levels before anyone can try it. What
 * must never happen is a knob that LOOKS applied and is not, so
 * {@see \App\Modules\Learning\Domain\Service\PlanKnobSupport} names, in Domain, exactly which ones
 * reach a trainer today — and the plan session reports it, rather than leaving it to be discovered.
 *
 * ## Easier by one step
 *
 * {@see easier()} is the whole of the adaptation rule: three misses in a row on a stage and this
 * word — only this word, only until the stage ends — is dealt on the настройках of a slower learner.
 * One step and not a slide to the floor: the point is to unstick a word, not to hand the learner a
 * card they cannot get wrong.
 */
final readonly class PlanKnobs
{
    /** How far the wrong options sit from the right one. */
    public const FAR = 'far';
    public const NEAR = 'near';
    public const CLOSE = 'close';

    /** What a typing card gives away before the learner starts. */
    public const HINT_FIRST_LETTER = 'first_letter';
    public const HINT_NONE = 'none';

    public const RATE_SLOW = 'slow';
    public const RATE_NORMAL = 'normal';

    /** Below three options a «выбери правильный» card is a coin toss, so this is the floor. */
    public const MIN_MC_OPTIONS = 3;

    public function __construct(
        /** Options on a choice card, the right one included. */
        public int $mcOptions,
        /** {@see FAR}, {@see NEAR} or {@see CLOSE}. */
        public string $distractorCloseness,
        /** Gaps cut out of the example on a cloze card. */
        public int $clozeBlanks,
        /** Chips dealt beyond the ones the answer needs, on a word bank card. */
        public int $bankExtra,
        /** {@see HINT_FIRST_LETTER} or {@see HINT_NONE}. */
        public string $typingHint,
        /** {@see RATE_SLOW} or {@see RATE_NORMAL}. */
        public string $ttsRate,
    ) {}

    /**
     * The shipped table, one row per level — the наряд's own numbers, and the only place they exist.
     *
     * They are seeded into `learning_mode_settings` (scope `plan`) by migration and read from there
     * at use time, so a level can be re-tuned without a deploy. This method is the seed AND the
     * fallback for a database whose plan rows somebody emptied.
     */
    public static function shipped(PlanLevel $level): self
    {
        return match ($level) {
            PlanLevel::Zero => new self(3, self::FAR, 1, 0, self::HINT_FIRST_LETTER, self::RATE_SLOW),
            PlanLevel::Basic => new self(3, self::NEAR, 1, 1, self::HINT_NONE, self::RATE_NORMAL),
            PlanLevel::Conversational => new self(4, self::NEAR, 2, 2, self::HINT_NONE, self::RATE_NORMAL),
            PlanLevel::Fluent => new self(4, self::CLOSE, 2, 2, self::HINT_NONE, self::RATE_NORMAL),
        };
    }

    /**
     * One step gentler on every knob that has a gentler setting, and unchanged where it is already
     * at the floor — a `zero` learner who gets stuck is not given two options and half a word.
     */
    public function easier(): self
    {
        return new self(
            mcOptions: max(self::MIN_MC_OPTIONS, $this->mcOptions - 1),
            distractorCloseness: match ($this->distractorCloseness) {
                self::CLOSE => self::NEAR,
                default => self::FAR,
            },
            clozeBlanks: max(1, $this->clozeBlanks - 1),
            bankExtra: max(0, $this->bankExtra - 1),
            typingHint: self::HINT_FIRST_LETTER,
            ttsRate: self::RATE_SLOW,
        );
    }

    /**
     * The wrong-option policy this closeness means in the trainer that already has one.
     *
     * `far` IS `distant` — the session's own neighbours, which is what makes a first meeting
     * winnable — and everything else is the ordinary distractor reader. The two vocabularies exist
     * because they answer to different owners: `options_policy` is the product-wide admission
     * matrix, `distractor_closeness` is what a plan asks for at a level. This is the one place they
     * are translated, so they cannot drift.
     */
    public function optionsPolicy(): OptionsPolicy
    {
        return $this->distractorCloseness === self::FAR ? OptionsPolicy::Distant : OptionsPolicy::Standard;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'mc_options' => $this->mcOptions,
            'distractor_closeness' => $this->distractorCloseness,
            'cloze_blanks' => $this->clozeBlanks,
            'bank_extra' => $this->bankExtra,
            'typing_hint' => $this->typingHint,
            'tts_rate' => $this->ttsRate,
        ];
    }

    /**
     * A stored row, read back. Anything missing or of the wrong shape falls back to the shipped
     * value for the level — a half-written row must not produce a card with zero options.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(PlanLevel $level, array $raw): self
    {
        $shipped = self::shipped($level);

        return new self(
            mcOptions: max(self::MIN_MC_OPTIONS, is_int($raw['mc_options'] ?? null) ? $raw['mc_options'] : $shipped->mcOptions),
            distractorCloseness: in_array($raw['distractor_closeness'] ?? null, [self::FAR, self::NEAR, self::CLOSE], true)
                ? (string) $raw['distractor_closeness']
                : $shipped->distractorCloseness,
            clozeBlanks: max(1, is_int($raw['cloze_blanks'] ?? null) ? $raw['cloze_blanks'] : $shipped->clozeBlanks),
            bankExtra: max(0, is_int($raw['bank_extra'] ?? null) ? $raw['bank_extra'] : $shipped->bankExtra),
            typingHint: in_array($raw['typing_hint'] ?? null, [self::HINT_FIRST_LETTER, self::HINT_NONE], true)
                ? (string) $raw['typing_hint']
                : $shipped->typingHint,
            ttsRate: in_array($raw['tts_rate'] ?? null, [self::RATE_SLOW, self::RATE_NORMAL], true)
                ? (string) $raw['tts_rate']
                : $shipped->ttsRate,
        );
    }
}
