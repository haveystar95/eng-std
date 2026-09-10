<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\PlanRequest;

/**
 * The two model calls of the plan, behind one seam: which vendor, which prompt file, which schema
 * and what it cost are Infrastructure's business. A transport or vendor failure throws; a reply
 * that decodes as JSON comes back whatever its content — the checks judge it.
 */
interface PlanModelPort
{
    public function buildPlan(PlanRequest $request): ModelReply;

    public function buildLesson(LessonRequest $request): ModelReply;

    /** The versions stamped on every plan and lesson — read from the prompt files' names. */
    public function planPromptVersion(): string;

    public function lessonPromptVersion(): string;
}
