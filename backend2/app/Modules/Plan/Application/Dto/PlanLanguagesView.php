<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * The two sides of a plan's pair as this deployment offers them (наряд LANG-1 §7).
 *
 * `targets` are the EFFECTIVE plan targets (`LanguageRoles::planTargets()` narrowed by `PLAN_LANGUAGES`),
 * `natives` are `LanguageRoles::planNatives()` — both codes, in the order the screens offer them. `names`
 * holds what a picker draws for every code of either list, read from the one catalogue
 * (`LanguageCatalog`, HYG-1): the endonym and the flag. Two wire shapes are cut from this one view —
 * `GET /plans/languages` (codes of the targets only, the shape build (21) reads) and `GET /languages`
 * (both sides, named) — so the two can never disagree about which languages there are.
 */
final readonly class PlanLanguagesView
{
    /**
     * @param  list<string>  $targets  language codes a plan may be built in
     * @param  list<string>  $natives  language codes a plan may be read in
     * @param  array<string, array{endonym: string, flag: string}>  $names  for every code of both lists
     */
    public function __construct(
        public array $targets,
        public array $natives,
        public array $names,
    ) {}
}
