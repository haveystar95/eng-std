<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\RouteStage;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StageState;
use App\Modules\Plan\Domain\ValueObject\TalkStage;

/**
 * THE STAGES OF A DAY ON THE ROUTE (PLAN-UI-3): which stages the day has and where each stands.
 *
 * Only the stages the day really has. A dealt day counts its cards: a stage with none is not on
 * the route, a stage whose cards are all answered is `done`, the first unfinished one is `current`,
 * the rest `locked` — the same walk the day room does, and a closed day has every stage `done`.
 *
 * A day not dealt yet has no cards to count. Its stages come from what it WILL deal: the outline
 * the dealer can draw for it when its material is written, otherwise what its type deals by the
 * canon (`docs/plan-v2.md` §6 — a scene day five card stages, a review words and «Повторение», the
 * rehearsal «Вспомнить»). Nothing of such a day is walked: it is all `locked`, except that the day
 * the learner may start today has its first stage `current`.
 *
 * THE SIXTH NODE (наряд CONV-1) is not one of these: the talk has no cards at all, so it is added
 * after them and its state is read off the journal of walked stages (наряд CONV-2, п. 2) — a replay
 * started after the stage was walked leaves the node walked.
 */
final class RouteStages
{
    /**
     * @param  array<string, array{total: int, answered: int}>  $tallies  by stage value; empty when no card is dealt
     * @param  list<Stage>  $outline  the stages the day will deal, when that is known; empty = go by the type
     * @param  bool  $hasConversation  does this day walk the sixth stage ({@see DayStages::walksConversation()})
     * @param  TalkStage|null  $talk  where its sixth stage stands; null — nothing of it yet
     * @return list<RouteStage>
     */
    public static function of(
        DayType $type,
        array $tallies,
        bool $closed,
        bool $availableToday,
        array $outline = [],
        bool $hasConversation = false,
        ?TalkStage $talk = null,
    ): array {
        $dealt = [];
        foreach (Stage::ofCards() as $stage) {
            if (($tallies[$stage->value]['total'] ?? 0) > 0) {
                $dealt[] = $stage;
            }
        }
        if ($dealt !== []) {
            return self::withTalk(self::walk($dealt, $tallies, $closed), $closed, $hasConversation, $talk);
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

        return self::withTalk($out, $closed, $hasConversation, $talk);
    }

    /**
     * The talk's own node, after the card stages (наряд CONV-1). It has no cards, so its state is
     * read off the journal of stages: walked — done; a talk going and the stage not walked — the
     * stage being walked; nothing started — the stage to walk once the cards are done.
     *
     * @param  list<RouteStage>  $stages
     * @return list<RouteStage>
     */
    private static function withTalk(array $stages, bool $closed, bool $hasConversation, ?TalkStage $talk): array
    {
        if (! $hasConversation) {
            return $stages;
        }
        $cardsDone = true;
        foreach ($stages as $stage) {
            if ($stage->state !== StageState::Done) {
                $cardsDone = false;
            }
        }
        $state = match (true) {
            $closed, $talk === TalkStage::Passed => StageState::Done,
            $talk === TalkStage::Open, $cardsDone && $stages !== [] => StageState::Current,
            default => StageState::Locked,
        };
        // A talk that is the CURRENT stage takes the «current» mark from the cards: only one node is current.
        if ($state === StageState::Current) {
            $stages = array_map(
                static fn (RouteStage $s): RouteStage => $s->state === StageState::Current ? new RouteStage($s->stage, StageState::Done) : $s,
                $stages,
            );
        }

        return [...$stages, new RouteStage(Stage::Conversation, $state)];
    }

    /**
     * What a day of this type deals by the canon, before any card exists.
     *
     * @return list<Stage>
     */
    public static function dealtBy(DayType $type): array
    {
        return DayStages::cardStagesOf($type);
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
        return array_values(array_filter(Stage::ofCards(), static fn (Stage $s): bool => in_array($s, $stages, true)));
    }
}
