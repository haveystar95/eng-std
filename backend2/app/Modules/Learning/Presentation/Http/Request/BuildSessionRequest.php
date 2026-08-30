<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class BuildSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'collection_id' => ['sometimes', 'nullable', 'string', 'size:26'], // scoped session
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'practice' => ['sometimes', 'boolean'],                            // free training, no scheduling
            'session_id' => ['sometimes', 'nullable', 'string', 'size:26'],    // client-generated ULID
            // `plan` hands the whole request to the plan's own assembler: the active plan, the day
            // the learner is on, stages and all. It exists so a client with one «Заниматься» button
            // does not have to know whether a plan is running — the server does.
            'scope' => ['sometimes', 'nullable', 'string', 'in:pool,plan'],
        ];
    }
}
