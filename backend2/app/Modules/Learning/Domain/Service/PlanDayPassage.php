<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\PlanDayStage;
use App\Modules\Learning\Domain\ValueObject\PlanDayStageState;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermCard;

/**
 * ПРОЙДЕН ЛИ ЭТАП, И КАКОЙ СЕЙЧАС — одна функция, из которой читают все (наряд DAY-GATE-1, Ч.1.1).
 *
 * Единственное определение «этап пройден» и «день пройден» в проекте. Ими пользуются вкладка «План»
 * (замок дня N+1), экран дня (список этапов), итог дня и сборщик сессии (что вообще раздавать) —
 * ровно потому, что живой прогон 05.09 уже ловил три экрана с тремя ответами про один день, и урок
 * был записан: считает сервер, и одним кодом.
 *
 * ## Пройден = пройден насквозь
 *
 * Этап пройден, когда ни одна его карточка сегодня ничего не должна
 * ({@see PlanStageLadder::owedStepsToday()}). «Ничего не должна» включает реплику, отвеченную
 * СЕГОДНЯ неверно: по правилу «один показ ступени в день» её следующий показ завтрашний, и держать
 * ею день значит запереть человека в дне, из которого сегодня нет выхода. Именно это и случилось у
 * владельца 07.09.
 *
 * Несказанное не теряется: промахнутая реплика возвращается завтра разогревом и швом, а сегодня её
 * можно взять ещё раз явным нажатием — «Повторить ошибки» ({@see PlanDayStage::Retrain}), и это
 * единственное исключение из показа раз в день.
 *
 * ## Спасателей здесь нет
 *
 * Спасательный наборе — карточки ПЛАНА, а не сцены (канон §5): они приходят в разогрев каждое утро и
 * ни один день не держат. Тот же довод, по которому они не держали «день пройден» и раньше
 * ({@see \App\Modules\Learning\Application\Service\PlanProgress}) — карточка, которую сборщик может
 * не собрать, держала бы день 1 открытым вечно, а с ним фокус, генерацию следующего дня и весь план.
 * Отбор — на вызывающем: сюда приходят карточки СЦЕНЫ.
 */
final class PlanDayPassage
{
    /**
     * СКОЛЬКО КАРТОЧЕК ЭТОТ ДЕНЬ ЕЩЁ ДОЛЖЕН СЕГОДНЯ, по этапам.
     *
     * Считаются карточки САМОГО дня: ни разогрева, ни шва (они принадлежат другим дням и приходят в
     * посадку сверх этого), ни прогона — прогон это не долг лестницы, а отдельный этап со своим
     * фактом ({@see stages()}).
     *
     * @param  list<PlanTermCard>  $cards  карточки сцены этого дня со своими стойками
     * @return array<string, int>  значение {@see PlanDayStage} => сколько карточек должно
     */
    public static function owed(array $cards): array
    {
        $owed = [
            PlanDayStage::Material->value => 0,
            PlanDayStage::Conversation->value => 0,
        ];

        foreach ($cards as $card) {
            $standing = $card->standing;
            $steps = PlanStageLadder::owedStepsToday($standing, $card->kind);
            if ($steps === []) {
                continue;
            }

            // К КАКОМУ ЭТАПУ ОТНОСИТСЯ ШАГ — по той же паре «полка + ступень», по которой карточке
            // назначается секция посадки ({@see PlanSessionSections::ofShelf()}). Второго правила
            // здесь заводить нельзя: разошлись бы список этапов и то, что сессия раздаёт.
            $stage = PlanDayStage::ofSection(
                PlanSessionSections::ofShelf($card->shelf, $standing->stage->value)
            );
            $owed[$stage->value] = ($owed[$stage->value] ?? 0) + count($steps);

            // …И РАЗГОВОР, КОТОРЫЙ ЗНАКОМСТВО ОТКРЫВАЕТ В ТОТ ЖЕ ДЕНЬ (решение 266): реплика, с
            // которой познакомились сегодня, сегодня же и говорится, поэтому её первый шаг ступени B
            // должен этому дню, хотя журнал о ступени B ещё ничего не знает.
            if ($standing->stage === PlanStage::A
                && ! $standing->answeredToday
                && PlanStageLadder::opensBSameDay($card->kind)
                && PlanStageLadder::firstStepOf(PlanStage::B, $card->kind) !== null
            ) {
                $owed[PlanDayStage::Conversation->value]++;
            }
        }

        return $owed;
    }

    /**
     * СПИСОК ЭТАПОВ ДНЯ в фиксированном порядке, каждый со своим состоянием.
     *
     * @param  list<PlanTermCard>  $cards  карточки сцены этого дня
     * @param  bool  $rehearsalDone  сцена уже прогонялась голосом (есть запись прогона) — ИЛИ
     *                               прогонять нечего: у сцены нет ни одного своего хода
     * @param  int  $retrainCards  сколько реплик можно взять ещё раз «Повторить ошибки»; 0 — строки
     *                             нет вовсе
     * @return list<array{stage: PlanDayStage, state: PlanDayStageState, cards: int}>
     */
    public static function stages(array $cards, bool $rehearsalDone, int $retrainCards = 0): array
    {
        $owed = self::owed($cards);

        $out = [];
        $currentTaken = false;
        foreach (PlanDayStage::REQUIRED as $stage) {
            $left = $stage === PlanDayStage::Rehearsal
                ? ($rehearsalDone ? 0 : 1)
                : ($owed[$stage->value] ?? 0);

            // ПОРЯДОК СИЛЬНЕЕ СЧЁТА: этап после текущего заперт, даже если карточек в нём ноль.
            // «Скажи сам» с нулём — это не «пройден», это «до него ещё не дошли»; открыть его над
            // неоконченным разговором значило бы просить сказать по памяти то, что человек ни разу
            // не выбирал.
            $state = match (true) {
                $currentTaken => PlanDayStageState::Locked,
                $left === 0 => PlanDayStageState::Done,
                default => PlanDayStageState::Current,
            };
            if ($state === PlanDayStageState::Current) {
                $currentTaken = true;
            }

            $out[] = ['stage' => $stage, 'state' => $state, 'cards' => $left];
        }

        // «ПОВТОРИТЬ ОШИБКИ» — строкой, только когда есть что повторять, и никогда не текущим
        // этапом: он ничего не открывает и ничего не держит.
        if ($retrainCards > 0) {
            $out[] = [
                'stage' => PlanDayStage::Retrain,
                'state' => PlanDayStageState::Current,
                'cards' => $retrainCards,
            ];
        }

        return $out;
    }

    /**
     * ДЕНЬ ПРОЙДЕН — все три обязательных этапа пройдены насквозь. Единственное определение.
     *
     * @param  list<array{stage: PlanDayStage, state: PlanDayStageState, cards: int}>  $stages
     */
    public static function passed(array $stages): bool
    {
        $done = 0;
        foreach ($stages as $row) {
            if ($row['state'] === PlanDayStageState::Done
                && in_array($row['stage'], PlanDayStage::REQUIRED, true)) {
                $done++;
            }
        }

        return $done === count(PlanDayStage::REQUIRED);
    }

    /**
     * ЭТАП, В КОТОРЫЙ ВЕДЁТ «ПРОДОЛЖИТЬ», или null — день пройден.
     *
     * @param  list<array{stage: PlanDayStage, state: PlanDayStageState, cards: int}>  $stages
     */
    public static function current(array $stages): ?PlanDayStage
    {
        foreach ($stages as $row) {
            if ($row['state'] === PlanDayStageState::Current
                && in_array($row['stage'], PlanDayStage::REQUIRED, true)) {
                return $row['stage'];
            }
        }

        return null;
    }
}
