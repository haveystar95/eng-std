<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the log filters. Dates especially: an unparseable `from` used to reach Postgres as a
 * literal and come back a 500 — with the filters living in the URL, a mangled shared link must be
 * a 422, not a crash.
 */
final class ListLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:admin
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'direction' => ['sometimes', 'in:inbound,outbound'],
            'provider' => ['sometimes', 'string', 'max:40'],
            'status' => ['sometimes', 'integer', 'min:100', 'max:599'],
            'status_class' => ['sometimes', 'in:2xx,4xx,5xx,error'],
            'purpose' => ['sometimes', 'in:generation,images,enrichment,realtime,recap,example_regen'],
            'user_id' => ['sometimes', 'string', 'size:26'],
            'collection_id' => ['sometimes', 'string', 'size:26'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'path' => ['sometimes', 'string', 'max:200'],
            'search' => ['sometimes', 'string', 'max:200'],
        ];
    }
}
