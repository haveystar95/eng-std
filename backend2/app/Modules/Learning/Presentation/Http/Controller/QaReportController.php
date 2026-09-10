<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Port\QaToolsDoor;
use App\Modules\Learning\Application\Port\QaReportStore;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /qa/report` — «жалоба» одним тапом с телефона (наряд DAY-GATE-1, Ч.0.5).
 *
 * За той же дверью, что и вход без пароля: `is_qa` И среда не production, и решает это СЕРВЕР
 * ({@see QaToolsDoor::isOpenFor()}) — второе правило про ту же дверь однажды разошлось бы с первым.
 * Закрытой двери отвечаем 404, а не 403: существует ли инструмент — не то, что обычный аккаунт
 * узнаёт.
 *
 * Тело — multipart: `report` (JSON-строка того, что видел клиент) и необязательный `screenshot`.
 * Поля отчёта НЕ валидируются по составу намеренно: он растёт от наряда к наряду, а «жалоба»,
 * отбитая 422 из-за нового ключа, — это потерянный слепок поломки, ради которой её и нажали.
 */
final class QaReportController
{
    /** Снимок экрана телефона — это единицы мегабайт; всё сверх — не наш сценарий. */
    private const MAX_SCREENSHOT_KB = 8192;

    /** Опись без снимка тоже отчёт, но 256 КБ текста — это уже не опись. */
    private const MAX_REPORT_BYTES = 262144;

    public function __construct(
        private readonly QaToolsDoor $door,
        private readonly QaReportStore $store,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = UserId::fromString((string) $request->user()?->getAuthIdentifier());
        if (! $this->door->isOpenFor($user)) {
            throw new NotFoundHttpException();
        }

        $request->validate([
            'report' => ['required', 'string', 'max:' . self::MAX_REPORT_BYTES],
            'screenshot' => ['nullable', 'file', 'mimes:png', 'max:' . self::MAX_SCREENSHOT_KB],
        ]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $request->input('report'), true) ?: [];

        $screenshot = $request->file('screenshot');
        $png = $screenshot instanceof UploadedFile ? (string) file_get_contents($screenshot->getRealPath()) : null;

        $id = $this->store->put(
            // Кто прислал — не из тела: тело пишет клиент, а аккаунт знает сервер.
            ['user_id' => $user->value] + $decoded,
            $png,
        );

        return new JsonResponse(['data' => ['id' => $id]], 201);
    }
}
