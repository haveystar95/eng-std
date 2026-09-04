<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\PlanDialogueMove;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;

/**
 * THE ORDER A SCENE IS SPOKEN IN — one answer, however the day was written.
 *
 * Since P2 v0.5 a day comes with its own chain (`docs/plan-dialogue.md` §9), stored on the day row
 * as `[{turn, term_id}, …]`. Every day written before it has shelves and no chain, and there are
 * plans halfway through on the phone right now. So this class answers the question once, for both:
 *
 *   **stored** — the model's own order, filtered to the cards the day still has. A turn pointing at
 *   a card that is no longer in the day is dropped rather than kept as a hole, for the same reason
 *   the generator drops one: a conversation with a silence in it is worse than a shorter one.
 *
 *   **derived** — pairs by `skill_ref`, which is the pairing the situational card already makes
 *   ({@see SituationalPrompt::pairedRoleLine()}): the «Тебе скажут» line serving the same ability as
 *   the reply IS the question that reply answers. Same tie-break, and for the same reason — the
 *   smallest term id is the first card of the shelf, ULIDs are written in the order the day composed
 *   itself, and a pairing that moved between sittings would be a rehearsal that changes its own
 *   premise.
 *
 * ## What the derived chain does NOT promise
 *
 * Perfect alternation. A day whose replies outnumber its role lines runs out of questions, and one
 * whose role lines outnumber its replies ends on two of them in a row. Both are accepted here and
 * refused for a FRESH answer ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::DIALOGUE_NOT_ALTERNATING}),
 * and that asymmetry is the point: a gate is what we buy with a paid call, and a fallback is what we
 * do with a day that was already paid for under different rules.
 *
 * ## Every card of the scene is in the chain exactly once
 *
 * Both paths, and it is what makes the chain safe to ORDER A SITTING with: a card the session owes
 * today but the chain never names would have to be dealt somewhere outside the conversation, which
 * is the three separate situational screens DAY-2 replaced. The stored path gets this from the gate
 * (`plan_day_dialogue_uncovered` counts the exceptions); the derived path gets it by construction —
 * every reply is emitted, and the role lines that never found a reply are appended at the end.
 *
 * Pure, in Domain, and it names no Vocabulary type: the caller flattens the day into
 * {@see SituationalCandidate}s, exactly as {@see SituationalPrompt}'s caller does.
 */
final class PlanDialogueChain
{
    /** `terms.shelf` — the interlocutor's own lines. */
    public const SHELF_HEAR = 'hear';

    /** The learner's shelves, in канон order: «Ты ответишь», then «Ты спросишь». */
    public const SHELF_SAY = 'say';

    public const SHELF_ASK = 'ask';

    /**
     * The scene's conversation, in the order it happens.
     *
     * @param  list<array{turn: string, term_id: string}>|null  $stored  the day's own chain, or null
     * @param  list<SituationalCandidate>  $dayCards  every card of the day, in any order
     * @return list<PlanDialogueMove>
     */
    public function for(?array $stored, array $dayCards): array
    {
        $byId = [];
        foreach ($dayCards as $card) {
            $byId[$card->termId] = $card;
        }

        return $stored === null || $stored === []
            ? $this->derive($dayCards)
            : $this->restore($stored, $byId);
    }

    /**
     * The stored chain, kept honest: a turn survives only if its card is still one of the day's, and
     * only if the side it claims matches the shelf the card actually stands on.
     *
     * The second half is not paranoia about the model — the gate already refused a mismatched ref —
     * it is about the day being re-read years later through a shelf that has since been renamed. A
     * `role` turn drawn over a card the learner is expected to SAY is exactly the defect Д-8 was.
     *
     * @param  list<array{turn: string, term_id: string}>  $stored
     * @param  array<string, SituationalCandidate>  $byId
     * @return list<PlanDialogueMove>
     */
    private function restore(array $stored, array $byId): array
    {
        $out = [];
        foreach ($stored as $turn) {
            $card = $byId[$turn['term_id']] ?? null;
            if ($card === null) {
                continue;
            }

            $isRole = $turn['turn'] === PlanDialogueMove::ROLE;
            if ($isRole !== ($card->shelf === self::SHELF_HEAR)) {
                continue;
            }

            $out[] = new PlanDialogueMove($turn['turn'], $card->termId);
        }

        return $out;
    }

    /**
     * A DAY WRITTEN BEFORE v0.5 — its conversation, paired out of the shelves.
     *
     * @param  list<SituationalCandidate>  $dayCards
     * @return list<PlanDialogueMove>
     */
    private function derive(array $dayCards): array
    {
        $hear = self::onShelf($dayCards, [self::SHELF_HEAR]);
        $learner = self::onShelf($dayCards, [self::SHELF_SAY, self::SHELF_ASK]);

        $used = [];
        $out = [];
        foreach ($learner as $reply) {
            $question = self::pairFor($reply, $hear, $used)
                ?? self::firstUnused($hear, $used);
            if ($question !== null) {
                $used[$question->termId] = true;
                $out[] = new PlanDialogueMove(PlanDialogueMove::ROLE, $question->termId);
            }
            $out[] = new PlanDialogueMove(PlanDialogueMove::LEARNER, $reply->termId);
        }

        // THE LINES NOBODY ANSWERED. A scene with more «Тебе скажут» than replies ends on them, and
        // that is the honest shape of «он попрощался, ты уже ответил всё, что должен» — the
        // alternative is a card of the day that no screen ever plays.
        foreach ($hear as $line) {
            if (! isset($used[$line->termId])) {
                $out[] = new PlanDialogueMove(PlanDialogueMove::ROLE, $line->termId);
            }
        }

        return $out;
    }

    /**
     * The unused «Тебе скажут» line serving the SAME ability as this reply — smallest id first.
     *
     * A reply with no `skill_ref` has no pair: `null === null` would otherwise match every
     * unlabelled role line of a day written before the gate, which is a pairing made of two
     * absences ({@see SituationalPrompt::pairedRoleLine()}, same sentence, same reason).
     *
     * @param  list<SituationalCandidate>  $hear  ordered by term id
     * @param  array<string, bool>  $used
     */
    private static function pairFor(SituationalCandidate $reply, array $hear, array $used): ?SituationalCandidate
    {
        $skillRef = self::text($reply->skillRef);
        if ($skillRef === null) {
            return null;
        }

        foreach ($hear as $line) {
            if (! isset($used[$line->termId]) && self::text($line->skillRef) === $skillRef) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @param  list<SituationalCandidate>  $hear
     * @param  array<string, bool>  $used
     */
    private static function firstUnused(array $hear, array $used): ?SituationalCandidate
    {
        foreach ($hear as $line) {
            if (! isset($used[$line->termId])) {
                return $line;
            }
        }

        return null;
    }

    /**
     * The day's cards on the given shelves, SHELF BY SHELF and by term id inside each.
     *
     * The two orderings are one rule seen twice: `say` before `ask` is канон §11's own order, and
     * the id inside a shelf is the order the day composed itself. Together they are the order the
     * scene was written in, which is the best guess at the order it is spoken in that a day with no
     * chain can offer.
     *
     * @param  list<SituationalCandidate>  $cards
     * @param  list<string>  $shelves
     * @return list<SituationalCandidate>
     */
    private static function onShelf(array $cards, array $shelves): array
    {
        $out = [];
        foreach ($shelves as $shelf) {
            $group = array_values(array_filter(
                $cards,
                static fn (SituationalCandidate $c): bool => $c->shelf === $shelf && self::text($c->text) !== null,
            ));
            usort($group, static fn (SituationalCandidate $a, SituationalCandidate $b): int => $a->termId <=> $b->termId);
            $out = [...$out, ...$group];
        }

        return $out;
    }

    private static function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
