<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * WHAT THE ROLE IS TOLD BEFORE ONE MOVE (`conversation_agent.v1`, наряд CONV-1, п. 4).
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
     * @param  list<array{id: string, title_native: string, about_native: string, role_target: string, role_native: string, key_lines: list<array{target: string, native: string}>}>  $checkpoints
     * @param  list<array{id: string, target: string, native: string}>  $phrases
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
        public array $phrases,
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
    ) {}

    /** @return list<string> the phrase ids the schema's enum is built from */
    public function phraseIds(): array
    {
        return array_map(static fn (array $phrase): string => $phrase['id'], $this->phrases);
    }
}
