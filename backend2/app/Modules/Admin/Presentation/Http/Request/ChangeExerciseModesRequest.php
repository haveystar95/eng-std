<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use App\Modules\Admin\Application\Query\GetExerciseModes;
use App\Modules\Admin\Application\Query\GetExerciseModesHandler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `modes` is the new set, in the order the practice rotation should walk it. Null is accepted only
 * on the per-user route, where it means "drop the override" — the controller enforces that.
 *
 * The allowed values come from the application itself (the enum, via the query), not from a list
 * repeated here: a hand-copied enum in a validation rule is how a newly built mode ends up
 * impossible to switch on.
 */
final class ChangeExerciseModesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $available = app(GetExerciseModesHandler::class)(new GetExerciseModes())->available;

        return [
            'modes' => ['present', 'nullable', 'array'],
            'modes.*' => ['string', Rule::in($available)],
        ];
    }

    /** @return list<string>|null */
    public function modes(): ?array
    {
        $modes = $this->validated('modes');
        if (! is_array($modes)) {
            return null;
        }

        /** @var list<string> $values */
        $values = array_values(array_unique(array_map(strval(...), $modes)));

        return $values;
    }
}
