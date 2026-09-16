<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One attempt at a card judged by meaning (наряд SESSION-1a, разд. 4): what the recogniser heard — present, and
 * possibly empty (silence is an attempt too) — and whether the frame was shown before it.
 */
final class JudgeCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'heard' => ['present', 'nullable', 'string', 'max:1000'],
            'hinted' => ['required', 'boolean'],
        ];
    }
}
