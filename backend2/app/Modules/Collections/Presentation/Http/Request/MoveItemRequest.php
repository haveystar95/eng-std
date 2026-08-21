<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class MoveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to_collection_id' => ['required', 'string', 'ulid'],
        ];
    }
}
