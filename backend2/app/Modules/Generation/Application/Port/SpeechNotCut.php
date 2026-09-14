<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use RuntimeException;

/**
 * Звук сценария пришёл, но на строки не разрезался (DAY-UI-3): пауз меньше, чем стыков, или куски не
 * похожи на свои строки по длине. Не транзиентно — повтор того же запроса стоит денег и запроса из
 * суточного лимита; строки остаются голосом телефона до следующей догрузки.
 */
final class SpeechNotCut extends RuntimeException
{
    public static function because(string $detail): self
    {
        return new self("speech could not be cut into its lines: {$detail}");
    }
}
