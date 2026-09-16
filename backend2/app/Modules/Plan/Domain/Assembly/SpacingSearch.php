<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

/**
 * THE SEARCH BEHIND {@see Spacing::apart()} (SESSION-1d): depth first, in the order of the waves, for the first
 * arrangement with the fewest places where two cards of a unit stand closer than the gap — none allowed first, then
 * one, then two… Every placement keeps the rules that are never bent: a unit's cards in their own order, no intro
 * overtaken by a card of its wave or a later one, the last card last. A branch is cut as soon as what is left of the
 * stage cannot hold a unit's remaining cards apart, and the whole search gives up (null) past its budget of
 * placements — then the waves are dealt greedily instead.
 *
 * @internal
 */
final class SpacingSearch
{
    /** @var array<int, int> the next card of every unit */
    private array $next;

    /** @var array<int, int> where every unit's last placed card stands */
    private array $lastAt = [];

    /** @var list<array{0: int, 1: int}|null> the arrangement so far; null is the last card */
    private array $order = [];

    private bool $lastPlaced = false;

    private int $nodes = 0;

    private readonly int $total;

    /**
     * @param  list<list<CardDraft>>  $units
     * @param  array<int, array<int, int>>  $index  every card's place in the order of the waves, by unit and card
     * @param  int|null  $lastUnit  the unit the last card belongs to
     */
    public function __construct(
        private readonly array $units,
        private readonly array $index,
        private readonly bool $hasLast,
        private readonly ?int $lastUnit,
        private readonly int $gap,
        private readonly int $budget,
    ) {
        $this->next = array_fill(0, count($units), 0);
        $this->total = array_sum(array_map(static fn (array $cards): int => count($cards), $units)) + ($hasLast ? 1 : 0);
    }

    /**
     * @return list<array{0: int, 1: int}|null>|null every placement as [unit, card] (null — the last card); null when
     *                                               the budget ran out before an arrangement was found
     */
    public function run(): ?array
    {
        for ($allowed = 0; $allowed <= $this->total; $allowed++) {
            $this->next = array_fill(0, count($this->units), 0);
            $this->lastAt = [];
            $this->order = [];
            $this->lastPlaced = false;
            $found = $this->place($allowed);
            if ($found === null) {
                return null;
            }
            if ($found) {
                return $this->order;
            }
        }

        return null;
    }

    /** True — the rest is arranged; false — a dead end; null — the budget is spent. */
    private function place(int $allowed): ?bool
    {
        if (++$this->nodes > $this->budget) {
            return null;
        }
        $pos = count($this->order);
        if ($pos === $this->total) {
            return true;
        }
        if (! $this->fits($pos, $allowed)) {
            return false;
        }

        $firstIntro = PHP_INT_MAX;
        foreach (array_keys($this->units) as $u) {
            if ($this->next[$u] === 0) {
                $firstIntro = min($firstIntro, $this->index[$u][0]);
            }
        }
        $candidates = [];
        foreach ($this->units as $u => $cards) {
            $c = $this->next[$u];
            if ($c >= count($cards) || $this->index[$u][$c] > $firstIntro) {
                continue;
            }
            $short = $this->short($u, $pos);
            if (! $short || $allowed > 0) {
                $candidates[] = [(int) $short, $this->index[$u][$c], $u];
            }
        }
        $unitCardsLeft = $this->total - $pos - ($this->hasLast && ! $this->lastPlaced ? 1 : 0);
        if ($this->hasLast && ! $this->lastPlaced && $unitCardsLeft === 0) {
            $short = $this->lastUnit !== null && $this->short($this->lastUnit, $pos);
            if (! $short || $allowed > 0) {
                $candidates[] = [(int) $short, PHP_INT_MAX, -1];
            }
        }
        sort($candidates);

        foreach ($candidates as [$short, , $u]) {
            if ($u === -1) {
                $this->order[] = null;
                $this->lastPlaced = true;
                $found = $this->place($allowed - $short);
                if ($found !== false) {
                    return $found;
                }
                array_pop($this->order);
                $this->lastPlaced = false;

                continue;
            }
            $c = $this->next[$u];
            $was = $this->lastAt[$u] ?? null;
            $this->order[] = [$u, $c];
            $this->lastAt[$u] = $pos;
            $this->next[$u] = $c + 1;
            $found = $this->place($allowed - $short);
            if ($found !== false) {
                return $found;
            }
            array_pop($this->order);
            $this->next[$u] = $c;
            if ($was === null) {
                unset($this->lastAt[$u]);
            } else {
                $this->lastAt[$u] = $was;
            }
        }

        return false;
    }

    /** Would the unit's next card stand closer than the gap to its last one? */
    private function short(int $u, int $pos): bool
    {
        return isset($this->lastAt[$u]) && $pos - $this->lastAt[$u] - 1 < $this->gap;
    }

    /**
     * Can the rest of the stage still hold every unit's remaining cards apart — each one gap after the last, with the
     * places the allowance may shorten? A necessary condition, not a sufficient one: it only cuts branches that are
     * surely dead.
     */
    private function fits(int $pos, int $allowed): bool
    {
        $left = $this->total - $pos;
        foreach ($this->units as $u => $cards) {
            $remaining = count($cards) - $this->next[$u] + ($u === $this->lastUnit && $this->hasLast && ! $this->lastPlaced ? 1 : 0);
            if ($remaining === 0) {
                continue;
            }
            $wait = isset($this->lastAt[$u]) ? max(0, $this->gap - ($pos - $this->lastAt[$u] - 1)) : 0;
            if ($wait + ($remaining - 1) * ($this->gap + 1) + 1 - $allowed * $this->gap > $left) {
                return false;
            }
        }

        return true;
    }
}
