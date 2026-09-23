<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\Inspection\BuildWindow;
use App\Modules\Plan\Application\Dto\Inspection\InspectedAudio;
use App\Modules\Plan\Application\Dto\Inspection\InspectedCard;
use App\Modules\Plan\Application\Dto\Inspection\InspectedPassage;
use App\Modules\Plan\Application\Dto\Inspection\InspectedPlan;
use App\Modules\Plan\Application\Dto\Inspection\InspectedScene;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTalk;
use DateTimeImmutable;

/**
 * THE PLAN'S ROWS AS STORED, FOR THE ADMIN'S PLAN PAGE (наряд ADM-1) — what the aggregate does not carry out: the build
 * stamps, the bills, the cards with what came back, the talks with their journal. Read-only; every query is by the plan,
 * its scenes or its days, each on an index.
 */
interface PlanInspectionReader
{
    /**
     * The plans whose code is this — the code is characters 5–10 of the plan's ULID (`01M2NKKGFF…` → `NKKGFF`), the name
     * reports have called plans by since GEN-3.
     *
     * @return list<string>
     */
    public function idsByCode(string $code): array;

    /** @return list<string> the learner's plans, newest first, deleted ones included */
    public function idsOf(string $userId): array;

    public function plan(string $planId): ?InspectedPlan;

    /** @return list<InspectedScene> in the plan's order */
    public function scenes(string $planId): array;

    /**
     * @param  list<string>  $dayIds
     * @return list<InspectedCard> by day, stage, position
     */
    public function cards(array $dayIds): array;

    /**
     * Every stored line of these scenes, in every voice.
     *
     * @param  list<string>  $sceneIds
     * @return list<InspectedAudio>
     */
    public function audios(array $sceneIds): array;

    /** @return list<InspectedTalk> the plan's talks with their lines, oldest first */
    public function talks(string $planId): array;

    /** @return list<InspectedPassage> */
    public function passages(string $planId): array;

    /**
     * How many build windows (plans, scene lessons, talks) of OTHER plans overlap each given window — a call inside a
     * window that others overlap may be theirs.
     *
     * @param  list<BuildWindow>  $windows
     * @return list<BuildWindow> the same windows with `others` filled in
     */
    public function withOthers(string $planId, array $windows): array;
}
