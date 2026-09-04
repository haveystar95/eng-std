<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Service\PlanDialogueLevel;

/**
 * ЧТО ПАРА (ПЛАН, ТЕРМИН) ДОКАЗАЛА ГОЛОСОМ И РУКАМИ — три факта, которых нет в журнале ответов.
 *
 * Лестница плана считается по append-only журналу и не хранится нигде
 * ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}), и это правило остаётся. Здесь —
 * ровно то, чего журнал не знает и знать не может, потому что журнал хранит РЕЖИМ и ВЕРДИКТ, а эти
 * три факта про ПОДАЧУ и про то, что случилось внутри одной карточки:
 *
 *   {@see $choiceStreak}  сколько выборов подряд закрыто без ошибки. Отличает неверную сборку от
 *                         неверного выбора, чего по журналу не сделать: тренажёр у них один.
 *   {@see $saidInRun}     реплика прозвучала голосом человека в прогоне сцены — ступень C.
 *   {@see $saidFast}      и прозвучала СРАЗУ (наряд SCENE-RUN, Ч.2, порог в `config/learning.php`).
 *                         Готовность канона — это «C + скорость» (`docs/plan-model.md` §4), и
 *                         скорость здесь не латентность карточки, а время до ключа внутри одного
 *                         прослушивания: карточка успела бы сойтись и за пятнадцать секунд.
 *
 * Ключ — ПАРА (план, термин), а не (пользователь, термин): термины дедуплицированы глобально, и
 * «сказал сам» в прошлом плане не является доказательством про этот. Прогресс пула
 * (`user_term_progress`) здесь не участвует вовсе и этой строкой не двигается.
 *
 * Обновляется ЛУЧШИМ результатом: один раз сказал сам — сказал сам. Прогон, в котором ту же реплику
 * пропустили, не отнимает у неё того, что было; отнимать умеет только человек, начав план заново.
 */
final readonly class PlanTermStage
{
    public function __construct(
        public string $planId,
        public string $termId,
        /** Безошибочных выборов подряд на ступени B; {@see PlanDialogueLevel}. */
        public int $choiceStreak = 0,
        /** Ключ реплики прозвучал голосом человека хотя бы в одном прогоне сцены. */
        public bool $saidInRun = false,
        /** …и хотя бы раз — в первые секунды прослушивания. */
        public bool $saidFast = false,
    ) {}

    /** Уровень, которым эта реплика раздаётся сейчас. */
    public function level(): PlanTurnLevel
    {
        return PlanDialogueLevel::forStreak($this->choiceStreak);
    }

    /** Тот же факт после ответа на ход диалога. */
    public function afterChoice(bool $correct): self
    {
        return new self(
            $this->planId,
            $this->termId,
            PlanDialogueLevel::after($this->choiceStreak, $correct),
            $this->saidInRun,
            $this->saidFast,
        );
    }

    /** Тот же факт после хода в прогоне сцены — лучшим результатом, никогда не хуже. */
    public function afterRun(bool $said, bool $fast): self
    {
        return new self(
            $this->planId,
            $this->termId,
            $this->choiceStreak,
            $this->saidInRun || $said,
            $this->saidFast || ($said && $fast),
        );
    }
}
