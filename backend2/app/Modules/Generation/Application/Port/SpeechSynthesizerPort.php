<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\SpeechScript;
use App\Modules\Generation\Application\Dto\SpokenLine;

/**
 * «СКАЖИ ЭТИ СТРОКИ ЭТИМИ ГОЛОСАМИ» — вся труба серверной озвучки за одним методом.
 *
 * Порт узкий сознательно: у нас нет продукта «синтез речи», у нас есть продукт «строки дня звучат».
 * С DAY-UI-3 единица — СЦЕНАРИЙ: диалог дня двумя голосами или пачка слов одним голосом, и вендор
 * спрашивается ОДИН раз на сценарий (лимит бесплатного Gemini — запросы, а не секунды). Адаптер,
 * который так не умеет, говорит строку за строкой внутри себя — наружу это не видно.
 *
 * Возвращает звук каждой строки в порядке сценария. Бросает {@see TransientSpeechError} на том, что
 * стоит повторить (лимит, 5xx, сеть), {@see SpeechNotCut} — когда звук не разрезался на строки, и
 * обычное исключение на том, чего повторять не надо.
 */
interface SpeechSynthesizerPort
{
    /** @return list<SpokenLine> one per turn, in the script's order */
    public function speakScript(SpeechScript $script): array;
}
