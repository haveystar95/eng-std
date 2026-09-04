<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Shared\Domain\Service\DistractorLength;

/**
 * ИЗ ЧЕГО СОБИРАЕТСЯ ОТВЕТ НА УРОВНЕ B+ — блоки реплики плюс чужие блоки (наряд SCENE-RUN, Ч.1.2).
 *
 * Уровень B — выбор из готовых реплик; B+ — та же реплика, но её больше нет целиком: на экране
 * лежат её собственные слова и связки вперемешку с чужими, и человек выкладывает ответ сам. Между
 * ними разница ровно в одном — сколько убрали, — и поэтому это не второй тренажёр, а вторая подача
 * первого ({@see \App\Modules\Learning\Domain\ValueObject\PlanTurnLevel}).
 *
 * ## Клавиатуры нет никогда
 *
 * Канон §9, железное правило продукта: «ни один обязательный шаг никогда не требует системной
 * клавиатуры изучаемого языка». Сборка тапом — то, чем это правило выполняется на длинном
 * высказывании; поэтому же прощения опечаток здесь нет и быть не может
 * ({@see \App\Modules\Learning\Domain\ValueObject\ExerciseMode::forgivesTypos()} — тапнутое не
 * опечатывается).
 *
 * ## Чужие блоки — из плана, и похожие
 *
 * Тот же пул, что у выбора: карточки ЭТОГО плана с полок «слова» и «связки». Не каталог — блок из
 * каталога это слово не из этого разговора; не реплики — реплику целиком не кладут блоком рядом с
 * её же словами. Похожие — по той же полосе длины, что судит варианты
 * ({@see DistractorLength}): среди четырёх своих слов по 5–7 букв блок `responsibility` не
 * дистрактор, а подсказка о том, какие блоки лишние.
 *
 * ## Мало чужих блоков — карточка всё равно раздаётся
 *
 * Ориентир {@see MIN_DECOYS}…{@see MAX_DECOYS}, и нижняя граница — ориентир, а не пол. Выбор при
 * голоде отбивается целиком, потому что выбор из двух это монетка; сборка при голоде остаётся
 * честной задачей — порядок слов и так надо вспомнить. А отбивать её нельзя: третье касание
 * ступени B это шаг чек-листа, и шаг, который нельзя раздать, — это ступень, которая не
 * закрывается, и день, который не проходится.
 */
final class PlanAssemblyBlocks
{
    /** Ориентир снизу: меньше двух чужих блоков — сборка почти без выбора, но всё ещё сборка. */
    public const MIN_DECOYS = 2;

    /** И сверху: экран блоков — не поле поиска, лишние тайлы перестают быть выбором и становятся шумом. */
    public const MAX_DECOYS = 4;

    /**
     * Собственные блоки реплики, в порядке, в котором она произносится.
     *
     * Пробелы, и только они. Пунктуация остаётся приклеенной к своему слову: «Sure, happy to.»
     * это три блока, а не пять, — человек собирает фразу, а не расставляет запятые, и отдельный
     * блок «,» был бы вопросом про типографику.
     *
     * @return list<string>
     */
    public static function own(string $line): array
    {
        $words = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }

    /**
     * Чужие блоки для этой реплики — до {@see MAX_DECOYS}, в порядке пула.
     *
     * Кандидат годится, если он ПОХОЖ ХОТЯ БЫ НА ОДИН собственный блок реплики: полоса длины
     * меряется от цели, а целей здесь несколько — у реплики из пяти слов нет одной длины, есть
     * пять. «Похож хоть на один» и означает «мог бы стоять в этом ряду»; «похож на все» отбивало бы
     * почти всё, а «похож хоть на что-нибудь» не отбивало бы ничего.
     *
     * @param  list<string>  $own  собственные блоки реплики ({@see own()})
     * @param  list<string>  $pool  тексты карточек плана с полок «слова» и «связки»
     * @return list<string>
     */
    public static function decoys(array $own, array $pool, DistractorLength $length): array
    {
        $taken = [];
        foreach ($own as $block) {
            $taken[self::key($block)] = true;
        }

        $out = [];
        foreach ($pool as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '' || count($out) >= self::MAX_DECOYS) {
                continue;
            }
            $key = self::key($candidate);
            if (isset($taken[$key])) {
                continue;
            }
            if (! self::fitsAny($own, $candidate, $length)) {
                continue;
            }
            $taken[$key] = true;
            $out[] = $candidate;
        }

        return $out;
    }

    /**
     * @param  list<string>  $own
     */
    private static function fitsAny(array $own, string $candidate, DistractorLength $length): bool
    {
        foreach ($own as $block) {
            // `null` — «обычная лексика», полоса по символам: блок это слово или связка, никогда не
            // реплика, и мерить его словами было бы мерить всегда единицу.
            if ($length->fits(null, $block, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /** Блоки сравниваются без регистра и без окружающей пунктуации: «Sure,» и «sure» — один блок. */
    private static function key(string $block): string
    {
        return mb_strtolower(trim($block, " \t\n\r\0\x0B.,!?;:«»\"'"));
    }
}
