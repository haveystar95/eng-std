<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Adapter;

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeZone;
use Throwable;

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
    private const DEFAULT_NATIVE_LANG = 'ru';

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

    public function nativeLangFor(UserId $user): string
    {
        $lang = $this->users->byId($user)?->profile?->nativeLanguage;

        return $lang !== null && $lang !== '' ? $lang : self::DEFAULT_NATIVE_LANG;
    }

    public function timezoneFor(UserId $user): DateTimeZone
    {
        $tz = $this->users->byId($user)?->profile?->timezone;
        if ($tz === null || $tz === '') {
            return new DateTimeZone('UTC');
        }

        // A stored zone is validated on write (the `timezone` rule), but be defensive: an unparseable
        // value must fall back, never throw inside review folding.
        try {
            return new DateTimeZone($tz);
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
