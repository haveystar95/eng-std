<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * The learner's zone and native language, read through Identity's Application. Memoised per request.
 *
 * THE ZONE DOES NOT HANG ON THE NATIVE (наряд LANG-1, валидатор). `PUT /profile` took any 2–5 characters as
 * `native_language` before наряд LANG-1 §7, so a profile may still hold «en_US» or «rus» — no language code at all.
 * Only {@see nativeLangFor()} is about the native, and only it refuses such a value, with the
 * `InvalidArgumentException` of {@see LanguageCode} naming it (the command that asks, `CreatePlanHandler`, answers the
 * learner the pair's 422 `language_pair_invalid`). The zone and the learner's today are read all the same: every plan
 * read, the day's window and the notification tick ask for them, and a stored native was never theirs to break.
 */
final class IdentityLearnerCalendar implements LearnerCalendar
{
    private const DEFAULT_NATIVE = 'ru';

    /** @var array<string, array{tz: DateTimeZone, native: string}> the native as the profile stores it, trimmed and lower-cased */
    private array $memo = [];

    public function __construct(private readonly UserReader $users) {}

    public function timezoneFor(UserId $user): DateTimeZone
    {
        return $this->read($user)['tz'];
    }

    public function todayFor(UserId $user, DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->setTimezone($this->timezoneFor($user))->setTime(0, 0);
    }

    /**
     * The profile's native; `ru` for a learner without a profile or with an empty one.
     *
     * @throws InvalidArgumentException when the profile's native is not a language code («en_US», «rus»)
     */
    public function nativeLangFor(UserId $user): LanguageCode
    {
        return new LanguageCode($this->read($user)['native']);
    }

    /** @return array{tz: DateTimeZone, native: string} */
    private function read(UserId $user): array
    {
        if (isset($this->memo[$user->value])) {
            return $this->memo[$user->value];
        }
        $view = $this->users->byId($user);
        $tz = 'UTC';
        $native = self::DEFAULT_NATIVE;
        if ($view?->profile !== null) {
            $tz = $view->profile->timezone;
            $native = $view->profile->nativeLanguage;
        }

        try {
            $zone = new DateTimeZone($tz);
        } catch (Exception) {
            $zone = new DateTimeZone('UTC');
        }
        $lang = trim($native) === '' ? self::DEFAULT_NATIVE : strtolower(trim($native));

        return $this->memo[$user->value] = ['tz' => $zone, 'native' => $lang];
    }
}
