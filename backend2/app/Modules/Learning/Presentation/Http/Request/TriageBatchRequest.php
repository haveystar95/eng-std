<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class TriageBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'triages' => ['required', 'array', 'min:1', 'max:200'],
            'triages.*.id' => ['required', 'string', 'size:26'],        // client-generated ULID
            'triages.*.term_id' => ['required', 'string', 'size:26'],
            'triages.*.verdict' => ['required', 'string', 'in:known,unknown,unsure'],
            'triages.*.decided_at' => ['required', 'date'],
            'triages.*.collection_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'triages.*.latency_ms' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
