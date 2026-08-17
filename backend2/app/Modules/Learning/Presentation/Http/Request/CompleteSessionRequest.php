<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // WHEN the learner finished. Optional: a client that does not send it is saying «now»,
            // which is the truth for an online finish and close enough for any other. What happened
            // during the run is never taken from the client — the server reads its own logs.
            'ended_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
