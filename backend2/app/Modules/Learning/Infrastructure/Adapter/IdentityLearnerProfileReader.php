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

    public function __construct(private UserReader $users) {}

    public function cefrLevelFor(UserId $user): CefrLevel
    {
        $profile = $this->users->byId($user)?->profile;

        return CefrLevel::tryFromLabel($profile?->cefrLevel) ?? self::DEFAULT_LEVEL;
    }
}
