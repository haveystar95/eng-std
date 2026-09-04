<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Shared\Domain\ValueObject\LineVoice;

/**
 * «СКАЖИ ЭТУ СТРОКУ ЭТИМ ГОЛОСОМ» — вся труба серверной озвучки за одним методом.
 *
 * Порт узкий сознательно: у нас нет продукта «синтез речи», у нас есть продукт «реплика сцены
 * звучит». Всё, что вендоры умеют сверх этого (потоковая отдача, разметка, клонирование голоса), в
 * порт не попало, потому что первый же адаптер, который этого не умеет, перестал бы быть заменимым.
 *
 * Бросает {@see TransientSpeechError} на том, что стоит повторить (лимит, 5xx, сеть), и обычное
 * исключение на том, чего повторять не надо. Разница читается джобой: первое — ретрай с backoff,
 * второе — реплика остаётся без озвучки, и день от этого не падает (наряд TTS-1, Ч.1.2).
 */
interface SpeechSynthesizerPort
{
    public function speak(string $text, string $lang, LineVoice $voice): SpokenLine;
}
