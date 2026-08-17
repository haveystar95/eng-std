<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\AdminExerciseModes;
use App\Modules\Learning\Application\Query\GetExerciseModeSettings;
use App\Modules\Learning\Application\Query\GetExerciseModeSettingsHandler;

/**
 * Reporting projection over Learning's own settings query. Admin owns no toggle data — it reads
 * Learning through its Application, exactly like every other admin screen.
 */
final readonly class GetExerciseModesHandler
{
    public function __construct(private GetExerciseModeSettingsHandler $settings) {}

    public function __invoke(GetExerciseModes $query): AdminExerciseModes
    {
        $view = ($this->settings)(new GetExerciseModeSettings($query->userId));

        return new AdminExerciseModes(
            available: $view->available,
            global: $view->global,
            override: $view->override,
            effective: $view->effective,
            noGrade: $view->noGrade,
            admission: $view->admission,
        );
    }
}
