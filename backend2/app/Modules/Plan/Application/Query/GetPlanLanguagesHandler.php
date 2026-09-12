<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\PlanLanguagesView;

/**
 * The server's list of plan languages (`config/plan.php` → `languages`). One source for the screen
 * that offers them and the request that accepts them: a client constant was wrong the day a
 * language was added.
 */
final readonly class GetPlanLanguagesHandler
{
    public function __construct(private PlanConfig $config) {}

    public function __invoke(GetPlanLanguages $query): PlanLanguagesView
    {
        return new PlanLanguagesView($this->config->languages);
    }
}
