<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use App\Modules\Admin\Application\Query\GetExerciseModes;
use App\Modules\Admin\Application\Query\GetExerciseModesHandler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One row of the admission matrix. The allowed modes come from the application itself (the enum,
 * via the query) rather than from a list repeated here — a hand-copied enum in a validation rule
 * is how a newly built trainer ends up impossible to place on the ladder.
 *
 * The finer rules (a reps threshold only means something once graduated, a learning step only 0–2)
 * are enforced in Learning's handler, where the ladder is defined, not duplicated here.
 */
final class ChangeModeAdmissionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $available = app(GetExerciseModesHandler::class)(new GetExerciseModes())->available;

        return [
            'mode' => ['required', 'string', Rule::in($available)],
            'min_acquisition' => ['required', 'string', Rule::in(['new', 'learning', 'graduated'])],
            'min_learning_step' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2'],
            'min_reps' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'options_policy' => ['sometimes', 'string', Rule::in(['distant', 'standard'])],
        ];
    }
}
