<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Domain\Service\PlanDialogueChain;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;

/**
 * СВОИ ХОДЫ ОДНОЙ СЦЕНЫ, в порядке разговора — один ответ на всех, кто про них спрашивает.
 *
 * Спрашивают трое, и по-разному: посадка строит из них прогон, экран плана считает по ним зрелость
 * сцены, а итог дня — строку прогона. Три собственных прохода по цепочке — это три места, где
 * «ход человека» может начать значить разное; в живом прогоне DAY-2-FIX уже стоила одна такая
 * рассинхронизация.
 *
 * Тонкий слой поверх {@see PlanDialogueChain}: он отвечает на вопрос «в каком порядке звучит сцена»,
 * а это — «какие из этих ходов твои».
 */
final class PlanSceneTurns
{
    public function __construct(private readonly PlanDialogueChain $chain = new PlanDialogueChain()) {}

    /**
     * Идентификаторы реплик, которые в этой сцене говорит человек.
     *
     * @return list<string>
     */
    public function of(PlanDayProgressView $day): array
    {
        $cards = [];
        foreach ($day->content as $termId => $view) {
            $cards[] = new SituationalCandidate($termId, $view->shelf, $view->skillRef, $view->text);
        }

        $out = [];
        foreach ($this->chain->for($day->dialogue, $cards) as $move) {
            if (! $move->isRole() && isset($day->content[$move->termId])) {
                $out[] = $move->termId;
            }
        }

        return $out;
    }
}
