<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * WHAT THE ROLE IS TOLD BEFORE ONE MOVE (`conversation_agent.v3`, наряд CONV-1, п. 4; наряд CONV-2, пп. 1, 4; наряд
 * FIX-3 §7: the talk's targets as constructions, and the one to open the door to now).
 *
 * Everything that changes from call to call — and NOTHING else: the prompt file itself is the
 * system message, byte for byte the same every time, so the vendor's prompt cache holds it
 * (the rule GEN-3 wrote for the lesson and the repair).
 *
 * `heard` travels in a field of its own and is named as the learner's SPEECH in the prompt: it is
 * the one input a stranger writes, and the prompt says in as many words that it is data.
 */
final readonly class ConversationAgentRequest
{
    /**
     * @param  list<array{id: string, title_native: string, about_native: string, role_target: string, role_native: string, key_lines: list<array{target: string, native: string, kind: string, partner: string, done: bool}>}>  $checkpoints
     * @param  list<array{id: string, kind: string, frame_target: string, frame_native: string, example_target: string|null, said: bool}>  $targets  the talk's targets, in the order the role leads to them
     * @param  list<array{speaker: string, text: string}>  $history  every line so far, oldest first
     * @param  'start'|'said'|'rescue'|'skip'  $turn
     */
    public function __construct(
        public string $targetLanguage,
        public string $nativeLanguage,
        public string $level,
        public string $roleTarget,
        public string $roleNative,
        public string $learnerRoleTarget,
        public string $learnerRoleNative,
        public array $checkpoints,
        public ?string $currentCheckpoint,
        public array $targets,
        public array $history,
        public string $turn,
        public string $heard,
        public int $turnsLeft,
        /**
         * How many of the learner's LAST moves in a row the role judged off topic — the fact the
         * prompt needs to obey «на повторе — попрощаться». The model cannot see it in HISTORY (that
         * carries texts, not verdicts), and a rule it cannot check is a rule it does not follow: the
         * live run of наряд CONV-1 caught exactly that.
         */
        public int $offTopicStreak = 0,
        /**
         * THE SECOND TRY OF THE SAME MOVE (наряд CONV-2): the first answer was refused by the server's guards
         * ({@see \App\Modules\Plan\Domain\Service\RoleLines}) — `learner_line` when it said a line of the learner (quoted
         * in `line`), `learner_echo` when it said a move of the learner back (наряд BACK-TAILS-2 §9, FIX-3 §11), `same_words`
         * when a rescue said the rescued line again, `own_line` when it said a line of its own again (quoted in `line`) and
         * `early_end` when it closed the talk with moves left (наряд FIX-3 §7). Null on every first try.
         *
         * @var array{reason: 'learner_line'|'learner_echo'|'same_words'|'own_line'|'early_end', said: string, line: string|null}|null
         */
        public ?array $redo = null,
        /** The target to open the door to now (наряд FIX-3 §7) — the server's choice ({@see \App\Modules\Plan\Domain\Service\ConversationLead}); null: none left. */
        public ?string $leadTo = null,
    ) {}

    /**
     * The same move asked again, with the reason the first answer was refused.
     *
     * @param  'learner_line'|'learner_echo'|'same_words'|'own_line'|'early_end'  $reason
     */
    public function redo(string $reason, string $said, ?string $line): self
    {
        return new self(
            $this->targetLanguage, $this->nativeLanguage, $this->level, $this->roleTarget, $this->roleNative,
            $this->learnerRoleTarget, $this->learnerRoleNative, $this->checkpoints, $this->currentCheckpoint,
            $this->targets, $this->history, $this->turn, $this->heard, $this->turnsLeft, $this->offTopicStreak,
            ['reason' => $reason, 'said' => $said, 'line' => $line], $this->leadTo,
        );
    }

    /** @return list<string> the target ids the schema's enums are built from */
    public function targetIds(): array
    {
        return array_map(static fn (array $target): string => $target['id'], $this->targets);
    }
}
