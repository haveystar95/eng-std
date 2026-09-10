<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Request;

use App\Modules\Plan\Domain\ValueObject\CardResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AnswerCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::enum(CardResult::class)],
            'attempts' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
