<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The plan aggregate — plan row, scenes and days together.
 *
 * {@see save()} writes the WHOLE aggregate from the snapshot in hand, so it belongs to a handler
 * that read the plan under a lock and writes it in the same transaction. A job that holds the
 * aggregate across an external call must never call it — its old scenes, its old status and its
 * old calendar would land on top of whatever happened during the call. Such a job writes its own
 * columns instead: {@see saveScene()}, {@see attachSceneImage()}, {@see attachCoverImage()}.
 */
interface PlanRepository
{
    public function findById(PlanId $id): ?Plan;

    /** The plan for its owner, or null for anybody else's — the API never says «exists but not yours». */
    public function findOwned(PlanId $id, UserId $owner): ?Plan;

    /** Locked for the transaction, so two workers cannot both accept a blueprint. */
    public function findOwnedForUpdate(PlanId $id, UserId $owner): ?Plan;

    public function findByIdForUpdate(PlanId $id): ?Plan;

    /** The learner's live plan (active or overdue), if any — the «one live plan» rule. */
    public function findLiveFor(UserId $owner): ?Plan;

    /**
     * What the tab shows: the live plan, or — when there is none — the newest plan that is built
     * and not started yet. A `ready` plan is a state of its own on the screen, not an absence.
     */
    public function findCurrentFor(UserId $owner): ?Plan;

    /** One scene, its row locked for the transaction: a lesson job claims its own row, not the plan. */
    public function findSceneForUpdate(PlanSceneId $id): ?PlanScene;

    /** The scene's own columns, and nothing else of the plan. */
    public function saveScene(PlanScene $scene): void;

    /** The day's numbers, refreshed from the cards they are counted off. */
    public function saveDayMetrics(PlanDayId $dayId, DayMetrics $metrics): void;

    /** The cover photo — written only while the plan still has none. */
    public function attachCoverImage(PlanId $id, Image $image): void;

    /** The scene's photo — written only while the scene still has none. */
    public function attachSceneImage(PlanSceneId $id, Image $image): void;

    public function save(Plan $plan): void;
}
