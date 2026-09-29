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
     * old ladder gave: the bare word's («marketing» → a supermarket) and a picture its day already
     * shows (DAY-UI-3). Nothing else rewrites a photo.
     */
    public function replaceImage(PlanTermId $id, Image $image): void;

    /**
     * The words and chunks of the plans that are not deleted whose photo was asked by the bare word —
     * no description of their own — for that re-ask (DAY-UI-3). Keyed by scene id.
     *
     * @return array<string, list<PlanTerm>>
     */
    public function photographedWithoutPrompt(?PlanId $planId): array;

    /**
     * The words and chunks of the plans that are not deleted whose photo repeats a picture their day
     * already shows — the scene's plate, or a word or chunk earlier in the scene — for the same re-ask
     * (DAY-UI-3: a day does not show one picture twice). The first holder of a picture keeps it. Keyed
     * by scene id.
     *
     * @return array<string, list<PlanTerm>>
     */
    public function repeatingDayPhotos(?PlanId $planId): array;
}
