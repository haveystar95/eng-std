<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * `GET /api/v1/health` — «какая сборка сейчас отвечает» (наряд DAY-GATE-1, Ч.0.4).
 *
 * Заведён ради одного вопроса, который 07.09 стоил дороже всего: телефон показывал одно поведение,
 * репозиторий — другой код, и не было способа сказать, ту ли сборку смотрят. Строка версии внизу
 * вкладки «План» склеивает две половины ответа: SHA клиента зашивается при сборке, SHA сервера
 * приезжает отсюда.
 *
 * НИ ОДНОГО СЕКРЕТА И НИ ОДНОГО СЧЁТЧИКА. Это не мониторинг и не `/up` (тот остаётся тем, чем был —
 * проверкой живости для Laravel): здесь только идентичность сборки, поэтому эндпоинт может быть
 * открытым, а открытым он должен быть, чтобы отвечать и до логина.
 *
 * Откуда берётся SHA: `APP_COMMIT` из окружения, иначе `storage/app/commit` — файл, который пишет
 * `scripts/stamp-build.sh`. Контейнер монтирует ТОЛЬКО `backend2/`, а `.git` лежит уровнем выше, в
 * корне репозитория, поэтому «спросить git» здесь физически нечем, и честный ответ на этот случай —
 * `unknown`, а не выдуманная строка.
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'status' => 'ok',
                'commit' => self::commit(),
                'env' => (string) config('app.env'),
            ],
        ]);
    }

    private static function commit(): string
    {
        $fromEnv = trim((string) config('app.commit', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $stamp = storage_path('app/commit');

        return is_file($stamp) ? (trim((string) file_get_contents($stamp)) ?: 'unknown') : 'unknown';
    }
}
