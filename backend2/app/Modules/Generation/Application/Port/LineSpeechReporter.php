<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * СКОЛЬКО РЕПЛИК ОСТАЛОСЬ БЕЗ ГОЛОСА — единственный след того, что озвучка не догнала день.
 *
 * Наряд TTS-1, Ч.1.2: ошибка провайдера не валит день. День остаётся `ready`, человек занимается,
 * а реплика без файла просто читается системным синтезом. Ровно поэтому недоозвученную сцену никто
 * не заметит — и ровно поэтому счётчик обязан быть: «озвучка не работает уже неделю» иначе
 * неотличимо от «озвучка ещё не включена».
 *
 * Порт, а не `Log::`, по той же причине, что и у соседей: Application не импортирует фреймворк, а
 * шов — это то, чем тест проверяет, что молчание было записано.
 */
interface LineSpeechReporter
{
    /**
     * @param  int  $spoken  lines that got audio on this pass
     * @param  int  $missing  lines that still have none after it
     */
    public function pass(string $collectionId, string $voiceKey, int $spoken, int $missing): void;

    /** One line the vendor refused for good — a retry would buy the same refusal. */
    public function refused(string $termId, string $voiceKey, string $reason): void;
}
