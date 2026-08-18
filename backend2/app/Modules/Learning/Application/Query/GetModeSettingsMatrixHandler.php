<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Port\ModeAdmissionReader;

final readonly class GetModeSettingsMatrixHandler
{
    public function __construct(private ModeAdmissionReader $admission) {}

    /**
     * @return list<array{mode: string, enabled: bool, position: int, min_acquisition: string, min_learning_step: int|null, min_successful_reviews: int|null, options_policy: string, source: string}>
     */
    public function __invoke(GetModeSettingsMatrix $query): array
    {
        return $this->admission->rowsFor($query->userId);
    }
}
