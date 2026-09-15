<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;

/**
 * The model calls of the plan, behind one seam: which vendor, which model, which prompt file, which schema and
 * what it cost are Infrastructure's business. A transport or vendor failure throws; a reply that decodes as JSON
 * comes back whatever its content — the checks judge it.
 */
interface PlanModelPort
{
    public function buildPlan(PlanRequest $request): ModelReply;

    public function buildLesson(LessonRequest $request): ModelReply;

    /**
     * P2R: one card of a written lesson, repaired — `{card}` in that card's shape; an exchange may come with
     * `frame_update`. Asked by the lesson build for a fatal card and by the `plan:repair-card` command.
     */
    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply;

    /** The seam judge: `{verdicts: [{id, reads}]}` — does each native sentence of the day read. Once a day. */
    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply;

    /** The versions stamped on every plan and lesson — read from the prompt files' names. */
    public function planPromptVersion(): string;

    public function lessonPromptVersion(): string;

    public function repairPromptVersion(): string;

    public function judgePromptVersion(): string;
}
