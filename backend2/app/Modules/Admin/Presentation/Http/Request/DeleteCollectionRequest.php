<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting a collection takes it away from every owner and subscriber at once, so the caller has to
 * type its title back. The check is enforced HERE and not only in the dialog: a confirmation that
 * lives only in the browser is a confirmation a misfired script skips.
 */
final class DeleteCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:admin
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'confirm_title' => ['required', 'string'],
        ];
    }
}
