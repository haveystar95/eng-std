<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Request;

use App\Modules\Shared\Domain\Service\LanguageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The actor can only ever edit their own profile (resolved from the bearer token).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // THE LANGUAGE THE LEARNER READS — any language the catalogue names (наряд LANG-1 §7). Not the
            // plan's nine natives: the same column is the support side of every collection and search, and
            // that side takes any language with a name (DECISIONS п. 85). A plan asked for with a native it
            // cannot be read in is refused by the plan itself (`language_pair_invalid`), not here.
            'native_language' => ['sometimes', 'string', Rule::in(LanguageCatalog::codes())],
            'target_language' => ['sometimes', 'string', 'min:2', 'max:5'],
            'cefr_level' => ['sometimes', 'string', 'in:A1,A2,B1,B2,C1,C2'],
            'daily_goal' => ['sometimes', 'integer', 'min:0', 'max:100'], // 0 = introduce no new terms
            'timezone' => ['sometimes', 'timezone:all_with_bc'], // IANA zone for calendar-day due rounding (F19); legacy aliases allowed — see GoogleLoginRequest
            'onboarded' => ['sometimes', 'boolean'], // onboarding-finish flag → server stamps onboarded_at (F1)
            'gender' => ['sometimes', 'nullable', 'in:female,male'], // the learner's lines in their language (GEN-2a); null clears
        ];
    }
}
