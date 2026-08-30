<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Build the session for one day of a plan.
 *
 * `dayIndex` null means «the day I am on» — the plan's own focus, which the server computes. A
 * client that names a day gets that day, and whether the session is STRICT (stages, checklists,
 * the focus moving) depends on whether the named day IS the focus: a day opened ahead of it, or
 * gone back to, is an ordinary soft run over its collection.
 */
final readonly class BuildPlanSession
{
    public function __construct(
        public UserId $actorId,
        public string $planId,
        public ?int $dayIndex = null,
        public ?StudySessionId $sessionId = null,
    ) {}
}
