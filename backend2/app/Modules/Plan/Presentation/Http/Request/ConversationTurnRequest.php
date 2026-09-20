<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Request;

use App\Modules\Plan\Domain\ValueObject\TurnKind;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ONE MOVE OF THE LEARNER (наряд CONV-1): what they said, or `rescue` («Не понял», кадр 37-7), or
 * `skip`. `agent` is not a move a client may send — the role's lines are the server's.
 */
final class ConversationTurnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', 'in:said,rescue,skip'],
            // Present and possibly empty: the recogniser heard nothing is an answer too.
            'heard' => ['present', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function turnKind(): TurnKind
    {
        return TurnKind::from((string) $this->validated('kind'));
    }

    public function heard(): string
    {
        $heard = $this->validated('heard');

        return is_string($heard) ? $heard : '';
    }
}
