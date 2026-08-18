<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Learning\Application\Query\GetModeSettingsMatrix as LearningGetModeSettingsMatrix;
use App\Modules\Learning\Application\Query\GetModeSettingsMatrixHandler as LearningGetModeSettingsMatrixHandler;

/**
 * Reporting projection over Learning's own matrix query. Admin owns no threshold data — it reads
 * Learning through its Application, exactly like every other admin screen.
 */
final readonly class GetModeSettingsMatrixHandler
{
    public function __construct(private LearningGetModeSettingsMatrixHandler $matrix) {}

    /**
     * @return list<array{mode: string, enabled: bool, position: int, min_acquisition: string, min_learning_step: int|null, min_successful_reviews: int|null, options_policy: string, source: string}>
     */
    public function __invoke(GetModeSettingsMatrix $query): array
    {
        return ($this->matrix)(new LearningGetModeSettingsMatrix($query->userId));
    }
}
