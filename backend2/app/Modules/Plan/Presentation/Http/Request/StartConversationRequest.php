<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * «Начать разговор» (кадр 37-5): the «Без подсказок» switch of the entry card, and `again` for
 * «Ещё раз» on the summary (кадр 37-12). Neither is required — a plain call continues the open talk.
 */
final class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'again' => ['sometimes', 'boolean'],
            'hints' => ['sometimes', 'boolean'],
        ];
    }

    public function again(): bool
    {
        return (bool) $this->validated('again', false);
    }

    public function hints(): bool
    {
        return (bool) $this->validated('hints', true);
    }
}
