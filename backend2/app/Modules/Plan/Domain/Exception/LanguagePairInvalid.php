<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/**
 * A plan asked for in a pair of languages it cannot be built in (наряд LANG-1 §7): the target is not one
 * of the deployment's plan targets (`LanguageRoles::planTargets()` narrowed by `PLAN_LANGUAGES`), the
 * learner's native — the profile's `native_language`, never the request's — is not one of
 * `LanguageRoles::planNatives()`, or the two are one language. Refused before anything is written, the
 * paywall included, so a pair the product cannot teach never costs a free plan.
 *
 * 422 and not 409: nothing about the learner's STATE would make the same request pass later — the
 * request itself names a pair that does not exist. `meta {target, native}` names both sides, so the entry
 * screen can say which of them is the problem (a native is changed in the profile, not here).
 */
final class LanguagePairInvalid extends PlanProblem
{
    public static function of(string $target, string $native): self
    {
        return new self("No plan is built from {$native} into {$target}.", ['target' => $target, 'native' => $native]);
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'language_pair_invalid';
    }

    public function problemTitle(): string
    {
        return 'A plan cannot be built in this pair of languages';
    }
}
