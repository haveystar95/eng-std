<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\SpeakableLine;

/**
 * КАКИЕ ИЗ ЭТИХ КАРТОЧЕК — реплики, то есть что вообще подлежит серверной озвучке.
 *
 * Полки решает ВЫЗЫВАЮЩИЙ, а не этот читатель: набор полок — конфиг (`generation.speech.shelves`),
 * потому что «озвучиваем ли мы „Ты ответишь“» — продуктовое решение, которое меняется без деплоя.
 * Наряд TTS-1 открывает трубу для `hear` и `rescue`; слова и связки остаются на системном голосе
 * сознательно (их много, они меняются чаще, и системный синтез читает их достаточно хорошо).
 */
interface SpeakableLineReader
{
    /**
     * @param  list<string>  $termIds
     * @param  list<string>  $shelves
     * @return list<SpeakableLine>
     */
    public function linesFor(array $termIds, array $shelves): array;
}
