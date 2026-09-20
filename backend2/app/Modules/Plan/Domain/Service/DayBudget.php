<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\ConversationType;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Stage;

/**
 * СКОЛЬКО ЗАНИМАЕТ ДЕНЬ — и что из этого стоит под потолком (решение владельца 21.09, наряд CONV-1).
 *
 * До шестого этапа это был один вопрос с одним ответом: минуты дня = минуты его карточек, и потолок
 * в 32 минуты стоял над ними. Разговор сделал из него два разных вопроса, и путать их дорого:
 *
 * - **ПОТОЛОК — про карточки.** «День дольше 32 минут — стоп до ворот» было сказано о правиле числа
 *   узнаваний: если ПЯТЬ этапов карточек стали дороже, значит раздача посчитана слишком щедро, и это
 *   чинят раздачей. Разговор в этот потолок не входит вовсе — у него свой бюджет
 *   ({@see ConversationRules::minutesFor()}), заданный не раздачей, а числом ходов;
 * - **ДЛИТЕЛЬНОСТЬ — про день целиком.** То, что ученик видит на экране и что уходит в его минуты, —
 *   карточки ПЛЮС разговор. Показать 30 минут дню, который идёт 33, — это соврать; поставить 33 под
 *   потолок 32 — это остановить сборку из-за того, что владелец заказал шестой этап.
 *
 * Отсюда две функции с разными именами, и ни одного места, где они одно и то же.
 */
final readonly class DayBudget
{
    /** Потолок ПЯТИ этапов карточек, минут (`plan.day_cards_budget`). Разговор в нём не считается. */
    public const CARDS_MINUTES = 32;

    public function __construct(
        private DayPace $pace,
        private ConversationRules $rules = new ConversationRules,
        private int $cardsMinutes = self::CARDS_MINUTES,
    ) {}

    /**
     * Минуты карточек дня — всех, что у него есть. Разговора среди них нет по построению: у шестого
     * этапа карточек не бывает.
     *
     * @param  list<DayCard>  $cards
     */
    public function cardsMinutes(array $cards): int
    {
        return DayPace::minutes($this->pace->secondsOf(array_filter(
            $cards,
            static fn (DayCard $card): bool => $card->stage() !== Stage::Conversation,
        )));
    }

    /** Минуты разговора этого дня — по его виду; 0 у дня, который разговора не несёт. */
    public function talkMinutes(DayType $type, bool $hasConversation): int
    {
        return $hasConversation ? $this->rules->minutesFor(ConversationType::forDay($type)) : 0;
    }

    /**
     * Сколько идёт день целиком — то, что видит ученик.
     *
     * @param  list<DayCard>  $cards
     */
    public function dayMinutes(array $cards, DayType $type, bool $hasConversation): int
    {
        return $this->cardsMinutes($cards) + $this->talkMinutes($type, $hasConversation);
    }

    /**
     * СТОП-УСЛОВИЕ СОСТАВА: карточки дня дороже потолка. Сигнал раздаче, а не запрет — день всё равно
     * собирается, но такой день не проходит ворота молча (тест канона `DayBudgetTest`).
     *
     * @param  list<DayCard>  $cards
     */
    public function overCardsCeiling(array $cards): bool
    {
        return $this->cardsMinutes($cards) > $this->cardsMinutes;
    }

    /** Сам потолок, чтобы отчёт и тест печатали его, а не переписывали. */
    public function ceilingMinutes(): int
    {
        return $this->cardsMinutes;
    }
}
