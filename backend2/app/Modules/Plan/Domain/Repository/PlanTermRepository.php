<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;

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

    /** The term's photo and its tone into their own columns, only while it has no photo — see {@see PlanRepository}. */
    public function attachImage(PlanTermId $id, Image $image): void;

    /** The ladder found nothing: the tone the card is painted with, only while it has no photo. */
    public function markImageMissing(PlanTermId $id, string $tone): void;

    /**
     * The term's photo written OVER the one it has — only for the backfill's re-ask of the photos the
     * bare word found before DAY-UI-3 («marketing» → a supermarket). Nothing else rewrites a photo.
     */
    public function replaceImage(PlanTermId $id, Image $image): void;

    /**
     * The words and chunks of the plans that are not deleted whose photo was asked by the bare word —
     * no description of their own — for that re-ask (DAY-UI-3). Keyed by scene id.
     *
     * @return array<string, list<PlanTerm>>
     */
    public function photographedWithoutPrompt(?PlanId $planId): array;
}
