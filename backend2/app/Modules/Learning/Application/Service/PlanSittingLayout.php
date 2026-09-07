<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\PlanDialogueView;
use App\Modules\Learning\Domain\Service\PlanSessionSections;
use App\Modules\Learning\Domain\Service\PlanSittings;

/**
 * WHAT ONE SITTING WILL DEAL, before a single card is built — {@see PlanSittingPlanner}'s answer.
 *
 * Specs in running order, the conversations they rest on, and the one number the screens print:
 * how many minutes the sitting is, at the configured seconds a card. The session builder assembles
 * cards out of the specs; the day-state census reads the counts and nothing else.
 */
final readonly class PlanSittingLayout
{
    /**
     * @param  list<array<string, mixed>>  $specs  in running order, each stamped with `section_code`
     * @param  list<PlanDialogueView>  $chains
     */
    public function __construct(
        public array $specs,
        public array $chains,
        private int $cardSeconds,
    ) {}

    /** Every card of the sitting — the day part and the прогон together. */
    public function cards(): int
    {
        return count($this->specs);
    }

    /** The cards before the прогон — what the day part holds. */
    public function dayCards(): int
    {
        return count(array_filter(
            $this->specs,
            static fn (array $s): bool => ($s['section_code'] ?? null) !== PlanSessionSections::SCENE_RUN,
        ));
    }

    /** «Материал» — the cards the learner meets and exercises (наряд DAY-FIX-3, Ч.4). */
    public function materialCards(): int
    {
        return PlanSittingPlanner::cardsOfKind($this->specs, PlanSittings::MATERIAL);
    }

    /** «Разговор» — the cards the learner says: the dialogue, the seam's lines, the прогон. */
    public function conversationCards(): int
    {
        return PlanSittingPlanner::cardsOfKind($this->specs, PlanSittings::CONVERSATION);
    }

    /** «около N минут» — cards × seconds, rounded UP to a whole minute; zero for an empty sitting. */
    public function minutes(): int
    {
        return self::minutesFor($this->cards(), $this->cardSeconds);
    }

    public function materialMinutes(): int
    {
        return self::minutesFor($this->materialCards(), $this->cardSeconds);
    }

    public function conversationMinutes(): int
    {
        return self::minutesFor($this->conversationCards(), $this->cardSeconds);
    }

    public static function minutesFor(int $cards, int $cardSeconds): int
    {
        if ($cards <= 0) {
            return 0;
        }

        return (int) ceil($cards * max(1, $cardSeconds) / 60);
    }

    /** ПРИСЕСТЫ — «Материал», then «Разговор». */
    /** @return list<int> */
    public function sittings(): array
    {
        return PlanSittingPlanner::sittingsOf($this->specs);
    }
}
