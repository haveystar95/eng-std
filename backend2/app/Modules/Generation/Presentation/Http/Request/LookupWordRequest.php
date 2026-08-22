<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Request;

use App\Modules\Generation\Domain\Service\SearchQueryLength;
use Illuminate\Foundation\Http\FormRequest;

final class LookupWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The SAME ceiling the instant hint refuses at, read from the same place — a field that
            // said «слишком длинно» while the button behind it happily bought the paragraph would
            // be two different products on one screen. See SearchQueryLength for why 120.
            //
            // A 422 rather than a polite 200 is right here and nowhere else: the client never fires
            // this call for a query it has already been told is too long, so reaching it at all
            // means something is wrong rather than long.
            'query' => ['required', 'string', 'min:1', 'max:' . app(SearchQueryLength::class)->max()],
        ];
    }
}
