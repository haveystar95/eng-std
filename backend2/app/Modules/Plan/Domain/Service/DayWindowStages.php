<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\ConversationState;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StageState;
use App\Modules\Plan\Domain\ValueObject\WindowStage;
use App\Modules\Plan\Domain\ValueObject\WindowStatus;

/**
 * THE STAGES OF A DAY IN ITS WINDOW (DAY-UI-2, кадры 23-0a…0c) — and the two numbers made of them.
 *
 * The rows are the stages the day deals, in walking order (a stage with no card is not a row); a
 * day with no card yet — its lesson is not written — has the stages its type deals. Where each row
 * stands is the window's own reading:
 *
 * - not started: every stage `locked`, the first one too — the frame says «впереди» five times and
 *   prints no number, because nothing is being walked yet (the route's first node may be current:
 *   there it says «start here», here the button says it);
 * - in progress: a stage with every card answered is `done`, the first one that is not is
 *   `current` — with the minutes its unanswered cards take by kind ({@see DayPace}) — the rest `locked`;
 * - passed: every stage `done`.
 *
 * Since наряд CONV-1 the last row of a day may be the TALK, which has no cards: it is `done` once
 * its journal says the talk ended, `current` when the cards are done and it is not over, `locked`
 * before that — and it never prints «N / M», because there is nothing to count.
 */
final class DayWindowStages
{
    /**
     * @param  list<DayCard>  $cards  the day's cards — dealt, or the dealer's outline of a day not opened
     * @param  list<Stage>  $withoutCards  what the day's type deals, for a day with no card yet
     * @param  bool  $hasConversation  does the day walk the sixth stage ({@see DayStages::walksConversation()})
     * @param  ConversationState|null  $conversation  where its talk stands; null — not started
     * @param  int  $conversationSeconds  how long the talk is reckoned to take ({@see ConversationRules})
     * @return list<WindowStage>
     */
    public static function of(
        array $cards,
        array $withoutCards,
        WindowStatus $status,
        DayPace $pace,
        bool $hasConversation = false,
        ?ConversationState $conversation = null,
        int $conversationSeconds = 0,
    ): array {
        $tallies = RouteStages::tally($cards);
        $out = [];
        $currentFound = false;
        foreach ($cards === [] ? $withoutCards : RouteStages::stagesOf($cards) as $stage) {
            $total = $tallies[$stage->value]['total'] ?? 0;
            $answered = $tallies[$stage->value]['answered'] ?? 0;
            $row = match (true) {
                $status === WindowStatus::Passed => WindowStage::done($stage),
                $status !== WindowStatus::InProgress => WindowStage::locked($stage),
                $total > 0 && $answered >= $total => WindowStage::done($stage),
                $currentFound => WindowStage::locked($stage),
                default => WindowStage::current($stage, $answered, $total, DayPace::minutes($pace->secondsOf(array_filter(
                    $cards,
                    static fn (DayCard $c): bool => $c->stage() === $stage && ! $c->isAnswered(),
                )))),
            };
            $currentFound = $currentFound || $row->state === StageState::Current;
            $out[] = $row;
        }

        if (! $hasConversation) {
            return $out;
        }

        // THE SIXTH ROW (наряд CONV-1). It has no cards, so it is neither counted nor paced by them:
        // «N / M» belongs to card stages, and the talk's minutes are what the talk is reckoned to
        // take. It becomes the current stage when the cards are done and the talk is not over.
        $out[] = match (true) {
            $status === WindowStatus::Passed, $conversation === ConversationState::Ended => WindowStage::done(Stage::Conversation),
            $status !== WindowStatus::InProgress => WindowStage::locked(Stage::Conversation),
            $currentFound => WindowStage::locked(Stage::Conversation),
            default => WindowStage::talking(Stage::Conversation, DayPace::minutes($conversationSeconds)),
        };

        return $out;
    }

    /**
     * The share of the day's stages that are walked — the compact header's bar: empty before the
     * start, three of five while walked, full once passed.
     *
     * @param  list<WindowStage>  $stages
     */
    public static function progress(array $stages): float
    {
        if ($stages === []) {
            return 0.0;
        }
        $done = count(array_filter($stages, static fn (WindowStage $s): bool => $s->state === StageState::Done));

        return round($done / count($stages), 2);
    }

    /**
     * The minutes the day still asks for: all of it before the start, what is left while it is
     * walked; a passed day asks for none (it says how long it took instead), and a day with no
     * cards cannot be estimated.
     *
     * @param  list<DayCard>  $cards
     */
    public static function minutesEstimate(array $cards, WindowStatus $status, DayPace $pace): ?int
    {
        if ($cards === [] || ! in_array($status, [WindowStatus::NotStarted, WindowStatus::InProgress], true)) {
            return null;
        }
        $left = $status === WindowStatus::NotStarted
            ? $cards
            : array_filter($cards, static fn (DayCard $c): bool => ! $c->isAnswered());

        return DayPace::minutes($pace->secondsOf($left));
    }
}
