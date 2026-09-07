<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\ValueObject\PlanId;

/**
 * ЖУРНАЛ ПРОЙДЕННЫХ ЭТАПОВ — append-only (наряд DAY-GATE-1, доработка).
 *
 * «Этап пройден» перестало быть мнением о сегодняшнем дне и стало событием: пересчёт по лестнице
 * порождает событие, но не заменяет его. Всё, что читает состояние дня, читает ЭТУ запись, и
 * поэтому полночь ничего не сбрасывает.
 *
 * Читается планом целиком: экран дня, вкладка «План», замок следующего дня и сборщик посадки
 * задают один и тот же вопрос — «что в этом плане уже пройдено», — и одного запроса на план хватает
 * всем четверым.
 */
interface PlanStagePassageRepository
{
    /**
     * Что уже пройдено в этом плане.
     *
     * @return array<int, list<string>> номер дня => значения {@see \App\Modules\Learning\Domain\ValueObject\PlanDayStage}
     */
    public function forPlan(PlanId $planId): array;

    /**
     * Записать закрытые этапы. Идемпотентно: событие, которое уже есть, не дублируется и не
     * переписывается — пройденное не разучивается, и «когда» у него одно.
     *
     * @param  list<array{day_index: int, stage: string}>  $rows
     * @param  string  $passedOn  календарная дата ученика (`Y-m-d`)
     */
    public function record(PlanId $planId, array $rows, string $passedOn): void;
}
