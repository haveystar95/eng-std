<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The provider and model are NOT validated against the registry here on purpose. An unknown one is
 * answered by the sandbox itself, in the answer box, as the sentence «нет ключа (… не задан)» or
 * «модель не входит в список» — which is the thing the operator needs to read. A 422 would put the
 * same fact in a place they are not looking.
 *
 * The prompt cap is a spend guard, not a schema: a 100k-character paste is a mis-click, and the bill
 * for it is real.
 */
final class PlaygroundGenerateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:32'],
            'model' => ['required', 'string', 'max:100'],
            'prompt' => ['required', 'string', 'max:32000'],
            // Omitted entirely when absent — several current models refuse the parameter.
            'temperature' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:2'],
        ];
    }
}
