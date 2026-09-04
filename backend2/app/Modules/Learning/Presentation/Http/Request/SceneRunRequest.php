<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use App\Modules\Learning\Domain\ValueObject\SceneRunOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ЗАВЕРШЁННЫЙ ПРОГОН СЦЕНЫ — ходы с исходами, и ни одного посчитанного числа.
 *
 * Клиент присылает, что случилось на каждом ходу; «сам N из M · сразу K» считает сервер. Число,
 * посчитанное на телефоне, было бы вторым источником правды о том, чего человек добился, и первым,
 * который разошёлся бы с зрелостью сцены.
 */
final class SceneRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Чужой план обработчик отвечает 404: существует ли такой ULID — не то, что посторонний
        // узнаёт по коду ответа.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scene_index' => ['required', 'integer', 'min:1', 'max:99'],
            'day_index' => ['required', 'integer', 'min:1', 'max:99'],
            // Прогон без ходов — не прогон: строка с `total = 0` описывала бы событие, которого не
            // было, и держала бы зрелость сцены на нуле навсегда.
            'turns' => ['required', 'array', 'min:1', 'max:40'],
            'turns.*.term_id' => ['required', 'string', 'size:26'],
            'turns.*.outcome' => ['required', Rule::in(array_column(SceneRunOutcome::cases(), 'value'))],
        ];
    }
}
