<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE SUMMARY OF ONE TALK (кадр 37-12, наряд CONV-1) — counted from the journal of turns, never
 * kept beside it: «Сказал сам 6 реплик», «Фразы дня в разговоре · 3 из 5», «Понял все вопросы ·
 * переспросил 2 раза», and what did not sound and comes back.
 *
 * Every number here is a projection of `conversation_turns`: the module's rule is that statistics
 * are projections that can be rebuilt, and a second count stored beside the journal is exactly the
 * thing that drifts from it.
 */
final readonly class ConversationOutcome
{
    /**
     * @param  list<string>  $phrasesUsed  scene-qualified ids ({@see ConversationPhrase::id()})
     * @param  list<string>  $notSaid  the ids of the plan's phrases that did not sound
     * @param  list<string>  $extraSaid  the constructions said that are no target — «ещё вспомнил» (наряд FIX-4 §2)
     */
    public function __construct(
        public int $saidCount,
        public array $phrasesUsed,
        public int $phrasesTotal,
        public array $notSaid,
        public bool $understoodAll,
        public int $notUnderstood,
        public int $rescues,
        public ?ConversationEnd $endedReason,
        public ?int $minutes,
        public array $extraSaid = [],
    ) {}

    public function phrasesUsedCount(): int
    {
        return count($this->phrasesUsed);
    }
}
