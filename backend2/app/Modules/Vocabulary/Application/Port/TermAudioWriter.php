<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\LineVoice;

/**
 * Записать строку об озвучке. Вставка идёт «если ещё нет»: гонку двух воркеров ловит уникальный
 * индекс `term_audios_voice_uidx`, а не проверка перед вставкой, — потому что между проверкой и
 * вставкой помещается второй воркер.
 */
interface TermAudioWriter
{
    /** True when the row was inserted; false when one for this term+voice+variant already existed. */
    public function write(
        string $audioId,
        string $termId,
        LineVoice $voice,
        string $format,
        string $path,
        int $bytes,
        ?int $durationMs,
        ?string $costUsd,
    ): bool;
}
