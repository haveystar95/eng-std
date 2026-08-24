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
            // The pair the pill is set to. Shape only — WHICH pairs this deployment serves is a
            // domain question, answered by SupportedLanguages with a 422 that names the pair, not
            // by an `in:` rule that would have to be kept in step with the config by hand.
            'source' => ['sometimes', 'string', 'size:2'],
            'target' => ['sometimes', 'string', 'size:2'],
            // Which half of that pair is the language being STUDIED. Optional: a client that does
            // not say leaves the server to break the tie as before (DECISIONS п. 147). `in:` is
            // right here where it was wrong above — «source|target» is the whole vocabulary of this
            // field and it cannot drift with the deployment.
            'taught_side' => ['sometimes', 'string', 'in:source,target'],
            // «Я нажал ещё раз». Only ever sent after the screen has been told the query was not
            // recognised, so it cannot be a way to buy the same card twice — a positive answer is
            // still served from the cache whatever this says.
            'retry' => ['sometimes', 'boolean'],
        ];
    }
}
