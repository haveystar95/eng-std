<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\LessonStatus;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * The day cannot be dealt because its scene's lesson is not there: still building (poll) — its
 * photos still being found counts as building (DAY-UI-3) — or failed (the client may ask for a retry
 * out loud). `meta.lesson_status` says which, in the wire's words.
 */
final class LessonNotReady extends PlanProblem
{
    public static function scene(PlanSceneId $scene, LessonStatus $status, ?string $reason): self
    {
        return new self(
            "The lesson of scene {$scene->value} is {$status->wire()}.",
            ['scene_id' => $scene->value, 'lesson_status' => $status->wire(), 'reason' => $reason],
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_lesson_not_ready';
    }

    public function problemTitle(): string
    {
        return 'The lesson is not ready';
    }
}
