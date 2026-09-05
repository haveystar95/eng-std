<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ONE TURN OF A SCENE'S CONVERSATION, as the SESSION speaks of it: whose it is, and which card.
 *
 * The generator's own turn ({@see \App\Modules\Generation\Domain\ValueObject\PlanDialogueTurn})
 * carries a SHELF ADDRESS — `hear[0]` — because at the moment the model answers, the cards have no
 * ids yet. By the time a sitting is built the address is meaningless and the term id is the only
 * name the card has, so the two are different types on purpose: one is «the third card the model
 * wrote on the hear shelf», the other is «this card».
 */
final readonly class PlanDialogueMove
{
    /** The other person's turn — a `hear` card, played aloud and never produced. */
    public const ROLE = 'role';

    /** The learner's own turn — a `say` or an `ask` card. */
    public const LEARNER = 'you';

    /**
     * THE TYPE OF THE EXCHANGE this turn belongs to (P2 v0.6, наряд DAY-FIX-2, Ч.1.1).
     *
     * `answer` — the other person asked or stated, the learner answers; `ask` — the other person
     * INVITED a question and the learner asks one. Null on a chain written before pairs existed:
     * the shelf still tells `say` from `ask` there, and this simply says nothing.
     */
    public const PAIR_ANSWER = 'answer';

    public const PAIR_ASK = 'ask';

    public function __construct(
        /** {@see ROLE} or {@see LEARNER}. */
        public string $turn,
        public string $termId,
        /** {@see PAIR_ANSWER} | {@see PAIR_ASK} | null — see the constants. */
        public ?string $pair = null,
    ) {}

    public function isRole(): bool
    {
        return $this->turn === self::ROLE;
    }

    /** @return array{turn: string, term_id: string, pair: string|null} */
    public function toArray(): array
    {
        return ['turn' => $this->turn, 'term_id' => $this->termId, 'pair' => $this->pair];
    }
}
