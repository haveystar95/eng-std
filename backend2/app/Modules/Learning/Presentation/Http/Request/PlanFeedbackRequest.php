<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * «Как прошло?» — the abilities the learner ticked, as positions in the plan's checkpoint list.
 *
 * `present` and not `required`: an empty list is a legitimate answer («ничего из этого не
 * пригодилось»), and it has to be tellable from the field being absent, which is what the nullable
 * column behind it means by «never asked».
 */
final class PlanFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is the handler's question — it answers a plan of another learner with a 404,
        // because whether a given ULID exists is not something a stranger gets to find out.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'checkpoints' => ['present', 'array'],
            // A plan's checkpoints are at most a handful; the ceiling is a sanity bound on a public
            // endpoint, not a product rule.
            'checkpoints.*' => ['integer', 'min:0', 'max:99'],
        ];
    }
}
