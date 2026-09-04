<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Adapter;

use App\Modules\Vocabulary\Application\Port\TermAudioStore;
use Illuminate\Support\Facades\Storage;

/**
 * Озвучка на диске приложения, на ПРИВАТНОМ диске.
 *
 * Приватный, а не `public`, сознательно: публичный диск раздаётся по `APP_URL`, а телефон ходит
 * через ngrok, домен которого в `APP_URL` не записан, — и «публичная» ссылка оказалась бы нерабочей
 * ровно на том устройстве, ради которого всё это делается. Файл отдаёт наш собственный маршрут
 * ({@see \App\Modules\Vocabulary\Presentation\Http\Controller\TermAudioController}) под тем же
 * bearer-токеном, что и остальной API, и адрес получается от текущего запроса — то есть от ngrok.
 *
 * Раскладка по первым двум символам id: один плоский каталог на несколько тысяч файлов — это
 * каталог, который неприятно листать и который файловая система читает линейно.
 */
final class FilesystemTermAudioStore implements TermAudioStore
{
    private const ROOT = 'line-audio';

    public function put(string $audioId, string $format, string $bytes): string
    {
        $path = self::pathFor($audioId, $format);
        Storage::disk($this->disk())->put($path, $bytes);

        return $path;
    }

    public function read(string $path): ?string
    {
        $disk = Storage::disk($this->disk());

        return $disk->exists($path) ? $disk->get($path) : null;
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk())->delete($path);
    }

    private static function pathFor(string $audioId, string $format): string
    {
        $shard = strtolower(substr($audioId, 0, 2));

        return self::ROOT . '/' . $shard . '/' . $audioId . '.' . $format;
    }

    private function disk(): string
    {
        return (string) config('generation.speech.disk', 'local');
    }
}
