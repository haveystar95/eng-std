<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Adapter;

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Reads the learner's CEFR level from the Identity module through its Application layer — never
 * its tables. An absent or unparseable level falls back to B1 (a mid default), so triage risk
 * scoring always has a baseline to compare a term against.
 */
final readonly class IdentityLearnerProfileReader implements LearnerProfileReader
{
    private const DEFAULT_LEVEL = CefrLevel::B1;
    private const DEFAULT_NEW_PER_DAY = 20;
    private const MAX_NEW_PER_DAY = 100;

    public function __construct(private UserReader $users) {}

    public function cefrLevelFor(UserId $user): CefrLevel
    {
        $profile = $this->users->byId($user)?->profile;

        return CefrLevel::tryFromLabel($profile?->cefrLevel) ?? self::DEFAULT_LEVEL;
    }

    public function newTermsPerDay(UserId $user): int
    {
        $profile = $this->users->byId($user)?->profile;
        if ($profile === null) {
            return self::DEFAULT_NEW_PER_DAY;
        }

        // 0 (new disabled) is honoured; anything above the cap is clamped down.
        return max(0, min(self::MAX_NEW_PER_DAY, $profile->dailyGoal));
    }
}
