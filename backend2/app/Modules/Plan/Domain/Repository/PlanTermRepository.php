<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

interface PlanTermRepository
{
    /** @return list<PlanTerm> in position order */
    public function forScene(PlanSceneId $sceneId): array;

    /**
     * @param  list<PlanSceneId>  $sceneIds
     * @return array<string, list<PlanTerm>> keyed by scene id
     */
    public function forScenes(array $sceneIds): array;

    /** @param list<PlanTerm> $terms replaces the scene's terms wholesale */
    public function replaceForScene(PlanSceneId $sceneId, array $terms): void;

    public function save(PlanTerm $term): void;
}
