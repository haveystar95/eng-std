<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\TalkStage;

/**
 * WHICH STAGES A DAY HAS (наряд CONV-1) — the one place the six of a scene day are written down.
 *
 * Until the talk with the agent, a day's stages could be read off its cards: a stage with a card is
 * a stage the day has. The sixth stage has no cards at all, so it is read off the journal of stages
 * instead: EVERY day has it (наряд ACC-1 §3 — the column `plan_days.has_conversation` and the rollout
 * switch that dealt five stages are gone), and a day that has nothing to talk about has it SKIPPED
 * ({@see TalkStage::Skipped}): behind the day, not drawn.
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
     * WILL THIS DAY WALK THE TALK? Every day does, but the one whose sixth stage is skipped — dealt with nothing to talk
     * about, or on five stages before the talk existed (наряд ACC-1 §3). A day not dealt yet has nothing in the journal
     * and walks it.
     *
     * @param  TalkStage|null  $talk  where the day's sixth stage stands; null — nothing of it yet
     */
    public static function walksTalk(?TalkStage $talk): bool
    {
        return $talk !== TalkStage::Skipped;
    }
}
