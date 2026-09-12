<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\RouteStage;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StageState;

/**
 * THE STAGES OF A DAY ON THE ROUTE (PLAN-UI-3): which stages the day has and where each stands.
 *
 * Only the stages the day really has. A dealt day counts its cards: a stage with none is not on
 * the route, a stage whose cards are all answered is `done`, the first unfinished one is `current`,
 * the rest `locked` — the same walk the day room does, and a closed day has every stage `done`.
 *
 * A day not dealt yet has no cards to count. Its stages come from what it WILL deal: the outline
 * the dealer can draw for it when its material is written, otherwise what its type deals by the
 * canon (`docs/plan-v2.md` §6 — a scene day five stages, a review words and speak, the rehearsal
 * speak only). Nothing of such a day is walked: it is all `locked`, except that the day the learner
 * may start today has its first stage `current`.
 */
final class RouteStages
{
    /**
     * @param  array<string, array{total: int, answered: int}>  $tallies  by stage value; empty when no card is dealt
     * @param  list<Stage>  $outline  the stages the day will deal, when that is known; empty = go by the type
     * @return list<RouteStage>
     */
    public static function of(DayType $type, array $tallies, bool $closed, bool $availableToday, array $outline = []): array
    {
        $dealt = [];
        foreach (Stage::ordered() as $stage) {
            if (($tallies[$stage->value]['total'] ?? 0) > 0) {
                $dealt[] = $stage;
            }
        }
        if ($dealt !== []) {
            return self::walk($dealt, $tallies, $closed);
        }

        $stages = $outline !== [] ? self::inWalkingOrder($outline) : self::dealtBy($type);
        $out = [];
        foreach ($stages as $index => $stage) {
            $out[] = new RouteStage($stage, match (true) {
                $closed => StageState::Done,
                $availableToday && $index === 0 => StageState::Current,
                default => StageState::Locked,
            });
        }

        return $out;
    }

    /**
     * What a day of this type deals by the canon, before any card exists.
     *
     * @return list<Stage>
     */
    public static function dealtBy(DayType $type): array
    {
        return match ($type) {
            DayType::Scene => Stage::ordered(),
            DayType::Review => [Stage::Words, Stage::Speak],
            DayType::Rehearsal => [Stage::Speak],
        };
    }

    /**
     * Cards → per-stage totals and answered counts, the shape {@see of()} reads.
     *
     * @param  list<DayCard>  $cards
     * @return array<string, array{total: int, answered: int}>
     */
    public static function tally(array $cards): array
    {
        $out = [];
        foreach ($cards as $card) {
            $key = $card->stage()->value;
            $out[$key] ??= ['total' => 0, 'answered' => 0];
            $out[$key]['total']++;
            if ($card->isAnswered()) {
                $out[$key]['answered']++;
            }
        }

        return $out;
    }

    /**
     * The stages some cards stand in, in walking order.
     *
     * @param  list<DayCard>  $cards
     * @return list<Stage>
     */
    public static function stagesOf(array $cards): array
    {
        return self::inWalkingOrder(array_map(static fn (DayCard $c): Stage => $c->stage(), $cards));
    }

    /**
     * @param  list<Stage>  $present
     * @param  array<string, array{total: int, answered: int}>  $tallies
     * @return list<RouteStage>
     */
    private static function walk(array $present, array $tallies, bool $closed): array
    {
        $out = [];
        $currentFound = false;
        foreach ($present as $stage) {
            $total = $tallies[$stage->value]['total'] ?? 0;
            $answered = $tallies[$stage->value]['answered'] ?? 0;
            $state = match (true) {
                $closed, $answered >= $total => StageState::Done,
                $currentFound => StageState::Locked,
                default => StageState::Current,
            };
            if ($state === StageState::Current) {
                $currentFound = true;
            }
            $out[] = new RouteStage($stage, $state);
        }

        return $out;
    }

    /**
     * @param  list<Stage>  $stages
     * @return list<Stage>
     */
    private static function inWalkingOrder(array $stages): array
    {
        return array_values(array_filter(Stage::ordered(), static fn (Stage $s): bool => in_array($s, $stages, true)));
    }
}
