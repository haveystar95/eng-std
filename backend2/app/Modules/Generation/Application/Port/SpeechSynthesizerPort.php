<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\SpeechBalance;
use App\Modules\Generation\Application\Dto\SpeechLine;
use App\Modules\Generation\Application\Dto\SpokenLine;

/**
 * «СКАЖИ ЭТИ СТРОКИ ЭТИМИ ГОЛОСАМИ» — вся труба серверной озвучки за одним интерфейсом с одной реализацией, ElevenLabs
 * (наряд TTS-2; двойник для тестов и `SPEECH_DRIVER=fake` — не реализация продукта).
 *
 * Порт узкий сознательно: у нас нет продукта «синтез речи», у нас есть продукт «строки дня звучат». Строка — реплика
 * собеседника или ученика, фраза, фраза с наполнением, слово — и каждая говорится своим вызовом своим голосом: вендор
 * берёт за символ, пакетировать нечего, а у отдельной строки свой файл и резать нечего. Строк сразу столько, сколько
 * позволяет тариф аккаунта; каждая купленная отдаётся сразу, поэтому отказ на пятой не теряет четыре оплаченные.
 *
 * Бросает {@see TransientSpeechError} на том, что стоит повторить позже (лимит одновременности, 5xx, сеть — после своих
 * коротких повторов) и {@see SpeechAccountError} — когда аккаунт не может платить или голос ему не разрешён (повтор
 * купит тот же отказ). Отказ прочитать один текст не бросается: строка пропускается и остаётся голосом телефона.
 */
interface SpeechSynthesizerPort
{
    /**
     * @param  list<SpeechLine>  $lines
     * @param  callable(int, SpokenLine): void  $spoken  called with the index into `$lines` as soon as a line is bought
     */
    public function speakLines(array $lines, callable $spoken): void;

    /**
     * What these lines would cost in the vendor's credits, before a single one is bought — the tariff's rate for each
     * line's characters, rounded up per line as the vendor rounds. An estimate for a cap to be checked against; the bill
     * itself is what `speakLines` hands back.
     *
     * @param  list<SpeechLine>  $lines
     */
    public function creditsFor(array $lines): int;

    /** What the vendor account has left this month; null — the vendor would not say (a key without that permission, a failed call). */
    public function balance(): ?SpeechBalance;
}
