<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * ПРИСЕСТЫ — how a day's sitting is cut into the pieces a person actually sits down for.
 *
 * ## ДВА ПРИСЕСТА: «МАТЕРИАЛ» И «РАЗГОВОР» (наряд DAY-FIX-3, Ч.4)
 *
 * It used to be «день = один присест ≤ 40, прогон — второй» (DAY-FIX-2, Ч.2.1). Then stage A
 * stopped closing on the intro alone (DAY-FIX-3, Ч.3): every word chooses its translation, every
 * connector is tiled, every reply is built from blocks BEFORE the conversation — and a day of
 * around seventy cards is not one sitting by any honest measure. So the cut moved to the seam the
 * material itself has: everything the learner MEETS and exercises, then everything they SAY.
 *
 *   «Материал»   the warm-up, the words and connectors, the introductions and their exercises —
 *                at most {@see MATERIAL_MAX_CARDS};
 *   «Разговор»   the scene's dialogue (stage B), the seam's lines by assembly, the прогон —
 *                at most {@see CONVERSATION_MAX_CARDS}.
 *
 * The owner accepted the day at ~70 cards / ~18 minutes across the two (07.09). What decides a
 * card's sitting is its SECTION and nothing else — the same code the seam captions are drawn from —
 * so the client and the planner cannot cut the day in two different places.
 *
 * ## Nothing here is a limit on the day
 *
 * Every task is in exactly one присест and every присест is non-empty, so `array_sum()` of the
 * result is the number of tasks it was given. That is the property the client's progress bar rests
 * on: «пройденное не сгорает» is only true if the parts add up to the whole. The CEILINGS are the
 * planner's business ({@see \App\Modules\Learning\Application\Service\PlanSittingPlanner::trimmed()}),
 * which trims before the cut; a sitting longer than its ceiling is a bug there, not a case here.
 */
final class PlanSittings
{
    /** The two kinds of sitting, as the wire names them. */
    public const MATERIAL = 'material';

    public const CONVERSATION = 'conversation';

    /**
     * THE CEILINGS — «материал ≤ 45, разговор ≤ 25» (решение владельца 07.09).
     *
     * Read from `config/learning.php → plan.budget.material_max_cards` /
     * `conversation_max_cards` by the planner; these constants are the domain's own statement of
     * the same numbers, and the two are pinned together by a test.
     */
    public const MATERIAL_MAX_CARDS = 45;

    public const CONVERSATION_MAX_CARDS = 25;

    /**
     * The task counts of each присест, in order: «Материал», then «Разговор». A sitting with no
     * card in it is not listed, so a day of intros alone is `[n]` and the final day — the прогон
     * of every scene — is `[n]` too.
     *
     * @param  list<string>  $sections  one section key per task, in the order the tasks are dealt
     * @return list<int>
     */
    public static function split(array $sections): array
    {
        return array_map(static fn (array $row): int => $row['cards'], self::plan($sections));
    }

    /**
     * The same cut, with each sitting NAMED — what the client draws the break screen and the
     * day's two minute counts from.
     *
     * @param  list<string>  $sections
     * @return list<array{kind: string, cards: int}>
     */
    public static function plan(array $sections): array
    {
        $counts = [self::MATERIAL => 0, self::CONVERSATION => 0];
        foreach ($sections as $section) {
            $counts[self::kindOf($section)]++;
        }

        $out = [];
        foreach ($counts as $kind => $cards) {
            if ($cards > 0) {
                $out[] = ['kind' => $kind, 'cards' => $cards];
            }
        }

        return $out;
    }

    /**
     * WHICH SITTING a card of this section falls into.
     *
     * A section key is `warmup` or `<code>#<day>`, as the planner keys it; the conversation is the
     * dialogue (stage B of the scene's lines, today's or the seam's), the прогон and the final
     * day's run-through. Everything else — the warm-up, the words, the introductions with their
     * exercises, a pre-shelf day's undivided block — is material.
     */
    public static function kindOf(string $section): string
    {
        $code = explode('#', $section, 2)[0];

        return in_array($code, [
            PlanSessionSections::DIALOGUE,
            PlanSessionSections::SCENE_RUN,
            PlanSessionSections::REHEARSAL,
        ], true) ? self::CONVERSATION : self::MATERIAL;
    }
}
