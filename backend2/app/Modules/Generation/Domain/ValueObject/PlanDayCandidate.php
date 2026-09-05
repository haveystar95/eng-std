<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * ONE DAY-SCENE, as the model wrote it — everything
 * {@see \App\Modules\Generation\Domain\Service\PlanDayValidator} needs, and nothing else.
 *
 * ## What v0.4 took away, and what it put in its place
 *
 * The three exact counts are gone (`termBudget`, `phraseCount`, `wordCount`, `chunkCount`) and so
 * are the numbered checkpoints. A day is no longer «fourteen cards split 8/2/4»: it is a SCENE with
 * shelves, and the shelf sizes are guidance the prompt states and this counts as a WARNING
 * ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::warnings()}). What replaced the
 * checkpoint index is {@see $skillIds}: every card names the ONE skill of the scene it serves, and
 * a card naming none — or naming somebody else's — is a carded violation.
 *
 * ## The three lists that exist to be COMPARED against
 *
 * {@see $rescueKit} and {@see $knownTexts} are what a new card must not be
 * ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::CLONE}); {@see $entityNames} is
 * what a card must not BE (a name is filler, never vocabulary). All three are handed in rather than
 * re-derived, so the gate and the prompt cannot disagree about what «already known» means — the
 * same rule the KNOWN block has followed since v0.2.
 */
final readonly class PlanDayCandidate
{
    /**
     * @param  list<PlanDayItem>  $items      every card of the day, all shelves together
     * @param  list<string>  $skillIds        the ids of the scene's skills, as the outline stored
     *                                        them. A `skill_ref` outside this list is a card
     *                                        serving an ability this scene never promised.
     * @param  list<string>  $entityNames     proper names of the scenario — «Иванов», «Zoom». They
     *                                        belong in the FILLER of a line and never on a card of
     *                                        their own (канон §7).
     * @param  list<string>  $rescueKit       the five phrases the server writes into day 1 and into
     *                                        every warm-up. The day may not teach them again.
     * @param  list<string>  $knownTexts      units introduced on an EARLIER day of this plan
     * @param  list<string>  $goalTerms       Latin-alphabet names the learner typed themselves —
     *                                        the one legitimate reason for foreign letters in a key
     * @param  string  $level                 `zero` | `basic` | `conversational` | `fluent`. Read
     *                                        for one rule only: the stop-list of basic vocabulary
     *                                        is FATAL from «Понимаю простое» up and a counter below
     *                                        it, because a `zero` learner legitimately meets «one»
     *                                        and «Monday» as cards.
     * @param  string  $sceneIntro            the scene's вводка, already written and shown on the
     *                                        day screen. A card that retells it is a warning
     *                                        ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::INTRO_REPEATED}).
     * @param  list<PlanDialogueTurn>  $dialogue  THE ORDER THE SCENE IS SPOKEN IN (P2 v0.5) — refs
     *                                        into the shelves above, alternating role and learner.
     *                                        EMPTY on every day written before v0.5, and empty is
     *                                        not «no dialogue»: the day is judged for a missing
     *                                        chain only when it came from a prompt that asks for
     *                                        one ({@see $expectsDialogue}), and the session builds
     *                                        one from the shelves for everything older
     *                                        ({@see \App\Modules\Learning\Domain\Service\PlanDialogueChain}).
     * @param  bool  $expectsDialogue      whether THIS answer was asked for a chain. False is what
     *                                        keeps a plan started on v0.4 finishable: its days are
     *                                        re-judged by the same validator on every repair, and a
     *                                        gate the prompt never asked for would refuse a day the
     *                                        learner is halfway through.
     */
    public function __construct(
        public string $supportLang,
        public string $targetLang,
        public array $items,
        public array $skillIds = [],
        public array $entityNames = [],
        public array $rescueKit = [],
        public array $knownTexts = [],
        public array $goalTerms = [],
        public string $level = 'basic',
        public string $sceneIntro = '',
        public array $dialogue = [],
        public bool $expectsDialogue = false,
        /**
         * THIS ANSWER WAS WRITTEN AS PAIRS (P2 v0.6) — so the minimum number of pairs is judged
         * ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::PAIRS_TOO_FEW}). The
         * court may have dropped some; a scene of three exchanges is not a scene. False on every
         * answer written by shelves, where nothing was ever paired.
         */
        public bool $expectsPairs = false,
    ) {}

    /** How many exchanges the chain holds — the learner's turns, which is one per pair. */
    public function pairCount(): int
    {
        $n = 0;
        foreach ($this->dialogue as $turn) {
            if ($turn->isLearner()) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * The cards of one shelf, in the order the model wrote them.
     *
     * @return list<PlanDayItem>
     */
    public function shelf(PlanShelf $shelf): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (PlanDayItem $i): bool => $i->arrayName() === $shelf->value,
        ));
    }

    /**
     * The card a dialogue turn points at, or null when it points at nothing.
     *
     * Looked up by SHELF AND POSITION, which is exactly what a ref is: `say[2]` is the third card
     * the model wrote on the `say` shelf, and {@see PlanDayItem::$index} is that position as the
     * model wrote it — the same address a violation carries and P2R repairs at.
     */
    public function at(PlanDialogueTurn $turn): ?PlanDayItem
    {
        if ($turn->shelf === null || $turn->index === null) {
            return null;
        }

        foreach ($this->items as $item) {
            if ($item->arrayName() === $turn->shelf->value && $item->index === $turn->index) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Every SPOKEN card of the day — the three line shelves together.
     *
     * @return list<PlanDayItem>
     */
    public function lines(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (PlanDayItem $i): bool => $i->kind === PlanDayItem::KIND_LINE,
        ));
    }
}
