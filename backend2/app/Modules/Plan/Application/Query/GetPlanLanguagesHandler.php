<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\PlanLanguagesView;
use App\Modules\Shared\Domain\Service\LanguageCatalog;
use App\Modules\Shared\Domain\Service\LanguageRoles;

/**
 * The server's lists of plan languages (наряд LANG-1 §7). One source for the screens that offer them and
 * the command that accepts them: a client constant was wrong the day a language was added.
 *
 * The targets are the deployment's effective list (`PlanConfig::$languages` — `LanguageRoles::planTargets()`
 * narrowed by `PLAN_LANGUAGES`); the natives are `LanguageRoles::planNatives()`, which no flag narrows — the
 * learner's own language is not a thing a deployment switches off for them. Names come from the one
 * catalogue; a code it did not know would be shown as itself rather than invented (the lists are held to
 * the catalogue by `LanguageRolesTest`, so that branch is a guard, not a path).
 */
final readonly class GetPlanLanguagesHandler
{
    public function __construct(private PlanConfig $config) {}

    public function __invoke(GetPlanLanguages $query): PlanLanguagesView
    {
        $targets = $this->config->languages;
        $natives = LanguageRoles::planNatives();

        $names = [];
        foreach ([...$targets, ...$natives] as $code) {
            $entry = LanguageCatalog::entry($code);
            $names[$code] = ['endonym' => $entry['endonym'] ?? $code, 'flag' => $entry['flag'] ?? ''];
        }

        return new PlanLanguagesView($targets, $natives, $names);
    }
}
