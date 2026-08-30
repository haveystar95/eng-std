<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\PlanDayCard;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;

/**
 * A2, second half — the order a day's material is INTRODUCED in.
 *
 * Two rules, and the second is the interesting one.
 *
 * **Easy first.** Within a group, by {@see \App\Modules\Shared\Domain\Service\DifficultyScorer}.
 * A day opening on its hardest sentence is a day the learner bounces off.
 *
 * **Where the replies go depends on the level, and the two answers are opposite.** Below
 * `conversational` the words come first: a learner who has never met «спина» cannot assemble
 * «Спина болит уже неделю» out of nothing, and showing them the reply first is showing them a wall.
 * From `conversational` up the replies come first: the reply is the useful unit, the words inside
 * it are recognised on the way past, and a day that opens on a vocabulary list reads like a
 * textbook rather than like a conversation the learner is about to have.
 *
 * Pure. It orders ids and nothing else, so it can be tested against the sandbox's real days without
 * a database — and so that when the session assembler picks this up (1b) it is picking up a
 * decision that has already been made and checked, not making a second one.
 */
final class PlanDayOrder
{
    /**
     * @param  list<PlanDayCard>  $cards
     * @return list<string>  term ids, in the order the day introduces them
     */
    public function order(array $cards, PlanLevel $level): array
    {
        $lines = [];
        $words = [];
        foreach ($cards as $card) {
            if ($card->isLine) {
                $lines[] = $card;
            } else {
                $words[] = $card;
            }
        }

        $this->sortByDifficulty($lines);
        $this->sortByDifficulty($words);

        $ordered = $level->wordsBeforeLines()
            ? [...$words, ...$lines]
            : [...$lines, ...$words];

        return array_map(static fn (PlanDayCard $c): string => $c->termId, $ordered);
    }

    /**
     * Easiest first, and an UNSCORED card sorts as if it were easy.
     *
     * Not «last» and not «hardest»: a null score means nobody has judged this term, which is the
     * state of every term written before plans existed. Sinking them to the bottom would put a
     * whole re-used vocabulary at the end of the day for a reason that is about our data and not
     * about the language. Ties keep their incoming order (`usort` is stable in PHP 8), which is the
     * model's own order — the one it wrote the day in.
     *
     * @param  list<PlanDayCard>  $cards
     */
    private function sortByDifficulty(array &$cards): void
    {
        usort(
            $cards,
            static fn (PlanDayCard $a, PlanDayCard $b): int => ($a->difficultyScore ?? 0) <=> ($b->difficultyScore ?? 0),
        );
    }
}
