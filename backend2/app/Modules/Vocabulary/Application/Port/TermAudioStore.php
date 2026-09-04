<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

/**
 * ГДЕ ЛЕЖАТ БАЙТЫ озвучки. The database row says which file; this says what a file IS.
 *
 * Отдельный порт от строки в базе по одной причине: строку читают на каждой сборке посадки, а файл
 * — только когда его качают. Смешать их значит таскать мегабайты через запрос, которому нужен URL.
 */
interface TermAudioStore
{
    /** Writes the bytes and returns the store-relative path they landed on. */
    public function put(string $audioId, string $format, string $bytes): string;

    /** The bytes back, or null when the file is gone (a row without a file is not an error here). */
    public function read(string $path): ?string;

    public function delete(string $path): void;
}
