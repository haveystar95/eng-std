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

/** The learner's zone and native language, read through Identity's Application. Memoised per request. */
final class IdentityLearnerCalendar implements LearnerCalendar
{
    private const DEFAULT_NATIVE = 'ru';

    /** @var array<string, array{tz: DateTimeZone, native: LanguageCode}> */
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

    public function nativeLangFor(UserId $user): LanguageCode
    {
        return $this->read($user)['native'];
    }

    /** @return array{tz: DateTimeZone, native: LanguageCode} */
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

        return $this->memo[$user->value] = ['tz' => $zone, 'native' => new LanguageCode($lang)];
    }
}
