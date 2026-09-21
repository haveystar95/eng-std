<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\Service\DayMetricsCalculator;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\Stage;

/**
 * THE NUMBERS OF A DAY — ITS CARDS AND ITS TALK (наряд BACK-TAILS-2 §8): the one place every writer of a day's metrics
 * asks — an answer, a verdict of the judge, the talk that walks the sixth stage, the close of the day.
 *
 * `minutes_spent` is the minutes of the day's cards, counted as ever ({@see DayMetricsCalculator}), PLUS the minutes of
 * the talk that WALKED the sixth stage — the first talk of the day that came to an end of its own ({@see
 * ConversationPassing}) — by the time it was talked ({@see Conversation::minutes()}, gaps of at most a minute). A replay
 * after it is an exercise on top of a walked day and adds nothing: «День пройден · 21 минута» is how long the day took.
 * The talk counts from the moment it walks the stage, not from the close — the summary 30-7 comes BEFORE «Закрыть день»,
 * and it printed «6 минут» of a day the close then called 9 (CLIENT-CONV-1b §5 п. 4).
 */
final readonly class DayMetricsOf
{
    public function __construct(
        private DayMetricsCalculator $calculator,
        private StagePassageRepository $passages,
        private ConversationRepository $conversations,
    ) {}

    /**
     * The day's metrics over its cards as they stand and the talk its journal of stages names.
     *
     * @param  list<DayCard>  $cards
     */
    public function of(PlanDayId $day, array $cards): DayMetrics
    {
        $walked = $this->passages->of($day, Stage::Conversation)?->conversationId;

        return $this->withTalk($cards, $walked === null ? null : $this->conversations->findById($walked));
    }

    /**
     * The same, with the talk that walked the stage in hand — the transaction that writes its passage.
     *
     * @param  list<DayCard>  $cards
     */
    public function withTalk(array $cards, ?Conversation $walked): DayMetrics
    {
        return $this->calculator->calculate($cards, $walked?->minutes() ?? 0);
    }
}
