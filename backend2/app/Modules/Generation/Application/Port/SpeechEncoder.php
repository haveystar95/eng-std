<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * PCM → MP3, и это единственное, что тут когда-либо будет.
 *
 * Порт узкий по той же причине, что и {@see SpeechSynthesizerPort}: у нас нет продукта «обработка
 * звука», у нас есть один вендор, который отвечает сырым PCM, и телефон, которому этот PCM не надо
 * качать. Всё остальное, что умеет кодировщик, в порт не попало.
 *
 * `null` — «кодировщика нет». Это НЕ ошибка: реплика сохраняется как WAV и звучит, просто весит
 * втрое больше. Труба, которая падала бы из-за отсутствия бинарника в образе, была бы хуже.
 */
interface SpeechEncoder
{
    /**
     * @param  string  $pcm  signed 16-bit little-endian, mono
     * @return string|null  mp3 bytes, or null when this build cannot encode
     */
    public function pcmToMp3(string $pcm, int $sampleRate): ?string;
}
