<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\PlanTermStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Learning\Domain\ValueObject\SceneMaturity;

/**
 * ГДЕ СТОИТ СЦЕНА И ГОТОВА ЛИ ОНА — наряд SCENE-RUN, Ч.3.
 *
 * Два разных вопроса из одних и тех же фактов, и путать их нельзя:
 *
 *   ЗРЕЛОСТЬ ({@see maturityOf()}) — три слова канона: познакомился → применяю → говорю сам. Это
 *   то, что человек читает на экране плана (кадр D·07).
 *
 *   ГОТОВНОСТЬ ({@see isReady()}) — «C + скорость» (`docs/plan-model.md` §4): сцена, которую
 *   говорят сам И достаточно быстро. На стойке отвечают за три секунды, а не за двенадцать, поэтому
 *   «говорю сам, но медленно» — честная зрелость и ещё не готовность.
 *
 * Готовность — ЧИСЛО, зрелость — СЛОВО, и это тоже не случайно: процентов на экранах плана нет
 * (наряд DAY-2-FIX), а API и админке доля готовых сцен нужна, потому что это первое честное число
 * готовности плана за всё время — до сих пор там был ноль.
 *
 * Чистая функция в Domain: на входе стойки ходов и то, что пары доказали голосом, на выходе слово и
 * булево. Ни базы, ни времени, ни конфига — порог приходит аргументом, потому что он продуктовое
 * суждение и живёт в `config/learning.php`.
 */
final class SceneCensus
{
    /**
     * Слово, которым описывается эта сцена.
     *
     * @param  list<PlanTermStanding>  $turns  стойки ходов `you` сцены
     * @param  list<PlanTermStage>  $stages  что те же ходы доказали голосом, в том же порядке
     */
    public static function maturityOf(array $turns, array $stages): SceneMaturity
    {
        if ($turns === []) {
            return SceneMaturity::Met;
        }

        // «Говорю сам» — КАЖДЫЙ ход прозвучал голосом. Пропуск и спасатель не считаются: первый это
        // «не сказал», второй — «сказал не сам», и оба честно оставляют сцену на предыдущем слове.
        $said = 0;
        foreach ($stages as $stage) {
            if ($stage->saidInRun) {
                $said++;
            }
        }
        if ($said === count($turns)) {
            return SceneMaturity::Speaking;
        }

        return PlanSceneRunGate::sceneIsReady($turns) ? SceneMaturity::Applying : SceneMaturity::Met;
    }

    /**
     * ГОТОВА ЛИ СЦЕНА — «говорю сам» И доля «сразу» не ниже порога.
     *
     * Доля считается от ВСЕХ ходов сцены, а не от сказанных: сцена, где половину пропустили, а
     * вторую половину сказали мгновенно, — это не «наполовину готово», это сцена, которую человек
     * не проходит.
     *
     * @param  list<PlanTermStanding>  $turns
     * @param  list<PlanTermStage>  $stages
     * @param  float  $fastShare  порог из `config/learning.php → plan.scene_run.ready_fast_share`
     */
    public static function isReady(array $turns, array $stages, float $fastShare): bool
    {
        if (self::maturityOf($turns, $stages) !== SceneMaturity::Speaking) {
            return false;
        }

        $fast = 0;
        foreach ($stages as $stage) {
            if ($stage->saidFast) {
                $fast++;
            }
        }

        return count($turns) > 0 && $fast / count($turns) >= $fastShare;
    }
}
