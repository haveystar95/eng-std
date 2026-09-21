<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Stage;

/**
 * WHICH STAGES A DAY HAS (наряд CONV-1) — the one place the six of a scene day are written down.
 *
 * Until the talk with the agent, a day's stages could be read off its cards: a stage with a card is
 * a stage the day has. The sixth stage has no cards at all, so that reading stops working — and it
 * cannot be «whatever the type deals» either, because a day OPENED before the talk existed keeps the
 * composition it was dealt with ({@see \App\Modules\Plan\Domain\Entity\PlanDay::hasConversation()},
 * the same rule FIX-2 §7 wrote for the cards). Hence one function, asked with both facts.
 *
 * - scene day: слова → фразы → диалог → слушаю и отвечаю → говорю сам → РАЗГОВОР;
 * - rehearsal: вспомнить → разговор (кадр 37-1);
 * - review: повторение (`repetition`, наряд BACK-TAILS-2 §3; words and phrases only when something comes back to them)
 *   → разговор (кадр 37-2).
 *
 * The talk's own row is added where the rows are built — {@see RouteStages::of()} for the route and
 * {@see DayWindowStages::of()} for the window — because only there is its journal at hand. What
 * lives here is the pair of facts both of them ask: which stages of CARDS a type deals, and whether
 * this day walks the talk at all.
 */
final class DayStages
{
    /**
     * The stages made of CARDS a day of this type deals, before any card exists.
     *
     * @return list<Stage>
     */
    public static function cardStagesOf(DayType $type): array
    {
        return match ($type) {
            DayType::Scene => [Stage::Words, Stage::Phrases, Stage::Dialogue, Stage::Listen, Stage::Speak],
            DayType::Review => [Stage::Words, Stage::Repetition],
            DayType::Rehearsal => [Stage::Recall],
        };
    }

    /**
     * WILL THIS DAY WALK THE TALK? A day already dealt says so itself — it keeps the composition it
     * was given ({@see PlanDay::hasConversation()}). A day not dealt yet will be dealt with today's
     * composition: the route and the window draw the future of a plan, not its past — and what
     * today's composition is, the switch says ({@see ConversationRules::ENABLED}), so it is asked
     * for and never assumed.
     *
     * @param  bool  $dealsTalk  does today's dealing include the sixth stage
     */
    public static function walksConversation(PlanDay $day, bool $dealsTalk): bool
    {
        return $day->openedAt() === null ? $dealsTalk : $day->hasConversation();
    }
}
