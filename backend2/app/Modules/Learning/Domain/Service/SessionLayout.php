<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\SessionSlot;

/**
 * Decides the RUNNING ORDER of a study session: where each card goes, so that a word introduced
 * at position 3 comes back at 6 and again at 12 rather than three times in a row.
 *
 * Pure and deterministic — no clock, no randomness. It has to be: the same session rebuilt from
 * the same inputs must deal the same order, or a retried request would re-deal a session the
 * learner is halfway through.
 *
 * What it enforces
 * ----------------
 *  * **A word introduced in a session is ANSWERED in that session.** The intro card is followed by
 *    recognition-forward 2–4 positions later and recognition-reverse 4–8 after that. Widening gaps,
 *    because that is what spacing means; a fixed gap would just be a longer block.
 *  * **A term is never introduced whose chain does not fit.** An intro whose recognitions fall off
 *    the end of the session is worse than no intro: the word is "met" and then not asked for a day.
 *    Such a term is deferred to the next session, whole.
 *  * **Intros never come as a block** (at most two consecutively). Note this holds structurally
 *    rather than by the counter alone: two intros at p and p+1 reserve recognition slots at p+2 and
 *    p+3, so the third position is already taken by something that is not an intro.
 *  * **Ladder cards and due repeats alternate** while both are available, so a first session inside
 *    an established deck does not feel like two sessions stapled together.
 *
 * The empty-first-session case falls out of the same rules with nothing special added: with no due
 * repeats to interleave, the ladder dilutes itself — intro, intro, recognition, recognition, intro…
 *
 * A HOLE (a position nothing can fill) is possible only once both primary queues are spent, i.e.
 * in the tail, and the result is compacted at the end. Closing a gap there costs nothing: a gap
 * exists to put other cards between two meetings of a word, and in the tail there are none left to
 * put — an uncompacted layout would end the session with dead air rather than with spacing.
 */
final class SessionLayout
{
    /** intro → recognition-forward */
    public const FIRST_GAP_MIN = 2;
    public const FIRST_GAP_MAX = 4;

    /** recognition-forward → recognition-reverse */
    public const SECOND_GAP_MIN = 4;
    public const SECOND_GAP_MAX = 8;

    public const MAX_CONSECUTIVE_INTROS = 2;

    /**
     * @param  list<array{term_id: string, step: int}>  $ladder  terms on rungs 0–2, in priority order;
     *                                                          `step` is the rung its FIRST card is
     *                                                          dealt at (0 intro, 1 or 2 recognition)
     * @param  list<string>  $repeats  graduated terms with one card each, in priority order
     * @return list<SessionSlot>
     */
    public function arrange(array $ladder, array $repeats, int $size): array
    {
        if ($size <= 0) {
            return [];
        }

        /** @var array<int, SessionSlot> $slots */
        $slots = [];
        $repeatIndex = 0;
        $consecutiveIntros = 0;
        $lastWasLadderHead = false;
        $pending = $ladder;

        for ($i = 0; $i < $size; $i++) {
            if (isset($slots[$i])) {
                // A reserved follow-up. It is by definition not an intro, so it breaks any run.
                $consecutiveIntros = 0;
                $lastWasLadderHead = false;

                continue;
            }

            $hasRepeat = $repeatIndex < count($repeats);
            $head = $this->nextHead($pending, $consecutiveIntros);

            if ($head === null && ! $hasRepeat) {
                continue; // a tail hole — compacted away below
            }

            // Alternate while both are available, so the two kinds interleave instead of forming
            // two blocks. When only one queue has anything, it simply keeps its turn.
            $takeRepeat = $head === null || ($hasRepeat && $lastWasLadderHead);

            if ($takeRepeat) {
                $slots[$i] = new SessionSlot($repeats[$repeatIndex]);
                $repeatIndex++;
                $consecutiveIntros = 0;
                $lastWasLadderHead = false;

                continue;
            }

            /** @var array{term_id: string, step: int} $entry */
            $entry = $pending[$head];
            $chain = $this->placeChain($slots, $i, $entry, $size);
            if ($chain === null) {
                // The whole chain does not fit before the end of the session. Defer the TERM, not
                // just its tail — and keep looking, because an entry already partway up the ladder
                // is a shorter chain and may still fit.
                array_splice($pending, $head, 1);
                $i--; // this position is still empty; try to fill it with something else

                continue;
            }

            foreach ($chain as $index => $slot) {
                $slots[$index] = $slot;
            }
            array_splice($pending, $head, 1);
            $consecutiveIntros = $entry['step'] === LearningLadder::STEP_INTRO ? $consecutiveIntros + 1 : 0;
            $lastWasLadderHead = true;
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * Index into $pending of the next head we may deal here, or null.
     *
     * The consecutive-intro cap is applied by SKIPPING intro entries, not by giving up: a term
     * already on a recognition rung is not an intro and can legitimately break the run.
     *
     * @param  list<array{term_id: string, step: int}>  $pending
     */
    private function nextHead(array $pending, int $consecutiveIntros): ?int
    {
        foreach ($pending as $index => $entry) {
            if ($entry['step'] === LearningLadder::STEP_INTRO && $consecutiveIntros >= self::MAX_CONSECUTIVE_INTROS) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * Lay out one term's remaining ladder cards from $head onwards, or null if they do not all fit.
     *
     * @param  array<int, SessionSlot>  $slots
     * @param  array{term_id: string, step: int}  $entry
     * @return array<int, SessionSlot>|null  index => slot, including the head
     */
    private function placeChain(array $slots, int $head, array $entry, int $size): ?array
    {
        $chain = [$head => new SessionSlot($entry['term_id'], $entry['step'])];
        $previous = $head;

        for ($step = $entry['step'] + 1; $step <= LearningLadder::STEP_RECOGNITION_REVERSE; $step++) {
            [$min, $max] = $this->gapBefore($step);
            $index = $this->firstFree($slots, $chain, $previous + $min, $previous + $max)
                // The window is full because other terms' cards landed in it. Take the first free
                // slot after it rather than dropping the card: a follow-up that is slightly late is
                // spacing, a follow-up that never happens is a word met and abandoned.
                ?? $this->firstFree($slots, $chain, $previous + $max + 1, $size - 1);

            if ($index === null || $index >= $size) {
                return null;
            }

            $chain[$index] = new SessionSlot($entry['term_id'], $step);
            $previous = $index;
        }

        return $chain;
    }

    /** @return array{int, int} the min/max distance from the previous card of the same term */
    private function gapBefore(int $step): array
    {
        return $step === LearningLadder::STEP_RECOGNITION_FORWARD
            ? [self::FIRST_GAP_MIN, self::FIRST_GAP_MAX]
            : [self::SECOND_GAP_MIN, self::SECOND_GAP_MAX];
    }

    /**
     * @param  array<int, SessionSlot>  $slots  already placed
     * @param  array<int, SessionSlot>  $chain  tentatively placed by this call
     */
    private function firstFree(array $slots, array $chain, int $from, int $to): ?int
    {
        for ($i = max(0, $from); $i <= $to; $i++) {
            if (! isset($slots[$i]) && ! isset($chain[$i])) {
                return $i;
            }
        }

        return null;
    }
}
