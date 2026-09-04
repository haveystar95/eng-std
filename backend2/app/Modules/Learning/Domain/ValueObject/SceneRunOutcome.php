<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ЧЕМ КОНЧИЛСЯ ОДИН ХОД ПРОГОНА — четыре исхода, и они не про «верно / неверно».
 *
 * Прогон не оценивает произношение и не ставит отметок: он отвечает на вопрос «смог ли человек
 * сказать это сам». Поэтому исходов четыре, а не два:
 *
 *   said       ключ реплики прозвучал голосом человека;
 *   saidFast   …и прозвучал в первые секунды прослушивания — вторая половина «готовности» канона §4;
 *   skipped    «Пропустить» руками или сторожем: пара получает `speaking/again`, разговор идёт
 *              дальше, никто не застревает;
 *   rescued    ход сделал спасатель. НЕ ошибка — «их использование не ошибка, а нормальный ход»
 *              (канон §8), — но и не «сам», поэтому свой исход, а не разновидность чужого.
 */
enum SceneRunOutcome: string
{
    case Said = 'said';

    case SaidFast = 'said_fast';

    case Skipped = 'skipped';

    case Rescued = 'rescued';

    /** Человек произнёс реплику сам — быстро или нет. */
    public function isSaid(): bool
    {
        return $this === self::Said || $this === self::SaidFast;
    }

    /** …и произнёс её сразу. Скорость бывает только у сказанного. */
    public function isFast(): bool
    {
        return $this === self::SaidFast;
    }
}
