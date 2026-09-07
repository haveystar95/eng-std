<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Qa;

use App\Modules\Learning\Application\Port\QaReportStore;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\Ulid;

/**
 * «Жалоба» на диск — `storage/qa-reports/<дата>-<ulid>.json` и `.png` рядом.
 *
 * Файлы, а не таблица, и это не срезанный угол: у отчёта нет ни запросов к нему, ни жизненного
 * цикла, ни связей — его читают глазами один раз и удаляют пачкой. Таблица дала бы миграцию,
 * модель и репозиторий ради `cat`.
 *
 * Имя начинается с ДАТЫ, потому что единственный способ, которым к этой папке обращаются, — «что
 * там было вчера вечером»: сортировка по имени и есть весь нужный индекс.
 */
final readonly class FileQaReportStore implements QaReportStore
{
    public function __construct(
        private Clock $clock,
        /** Абсолютный путь к папке отчётов — `storage_path('qa-reports')` из провайдера. */
        private string $directory,
    ) {}

    public function put(array $report, ?string $screenshotPng): string
    {
        $id = Ulid::generate();
        $name = $this->clock->now()->format('Ymd-His') . '-' . $id;

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        // Снимок пишется ПЕРВЫМ: json — это опись, и опись, ссылающаяся на файл, которого нет,
        // хуже отсутствующей.
        $screenshot = null;
        if ($screenshotPng !== null && $screenshotPng !== '') {
            $screenshot = $name . '.png';
            file_put_contents($this->directory . '/' . $screenshot, $screenshotPng);
        }

        file_put_contents(
            $this->directory . '/' . $name . '.json',
            json_encode(
                [
                    'id' => $id,
                    'received_at' => $this->clock->now()->format(DATE_ATOM),
                    'screenshot' => $screenshot,
                ] + $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        return $id;
    }
}
