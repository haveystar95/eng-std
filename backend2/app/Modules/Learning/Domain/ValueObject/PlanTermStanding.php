<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * WHERE ONE WORD OF A PLAN STANDS right now — the whole answer, computed from the review log by
 * {@see \App\Modules\Learning\Domain\Service\PlanStageLadder} and stored nowhere.
 *
 * Read it as a sentence: this word is on stage `$stage`, its checklist looks like `$checklist`, the
 * next card it owes is `$nextMode`, and if `$nextMode` is null it owes nothing today — either
 * because the stage closed and the next one opens after a night (`$waitingForNight`) or because all
 * three stages are behind it (`$finished`).
 */
final readonly class PlanTermStanding
{
    /**
     * @param  list<array{mode: string, ordinal: int, done: bool}>  $checklist
     *         the stage's trainers in their fixed order, each marked closed or not. Modes this term
     *         cannot be drilled in are NOT here at all: they fall out of the checklist rather than
     *         blocking it, so a term with no distractors is never stuck waiting for `pick_correct`.
     */
    public function __construct(
        public PlanStage $stage,
        public array $checklist,
        /** The trainer this word is owed next, or null when it owes nothing today. */
        public ?ExerciseMode $nextMode,
        /** Every trainer of the stage is closed. */
        public bool $stageComplete,
        /** Closed, but only today — the next stage opens in the session after the night. */
        public bool $waitingForNight,
        /** The LAST stage is closed and its night has passed: this card has lived its stages. */
        public bool $finished,
        /**
         * Three misses in a row on this stage, so this word — and only this word, and only until the
         * stage ends — is dealt on gentler knobs ({@see PlanKnobs::easier()}).
         */
        public bool $softened,
        /**
         * THIS CARD IS ON ITS LAST STAGE — and «last» depends on what the card is.
         *
         * A line has no stage C ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}):
         * «нечего печатать по буквам» is the whole argument, so a line standing on B is as far as a
         * line goes. A word or a connector reaches its last stage at C. Readiness counts THIS and
         * not `stage === C`, because under the old rule every line in the plan would have held the
         * percentage down for ever by standing on a rung that does not exist for it.
         */
        public bool $ready = false,
    ) {}

    /** Is this word's checklist closed for good? What «готовность слова» means. */
    public function isReady(): bool
    {
        return $this->finished;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage->value,
            'checklist' => $this->checklist,
            'next_mode' => $this->nextMode?->value,
            'stage_complete' => $this->stageComplete,
            'waiting_for_night' => $this->waitingForNight,
            'finished' => $this->finished,
            'softened' => $this->softened,
            'ready' => $this->ready,
        ];
    }
}
