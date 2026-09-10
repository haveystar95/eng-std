<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\VersionsView;
use App\Modules\Plan\Application\Service\PlanViews;

final readonly class GetVersionsHandler
{
    public function __construct(private PlanViews $views) {}

    public function __invoke(GetVersions $query): VersionsView
    {
        return $this->views->versions();
    }
}
