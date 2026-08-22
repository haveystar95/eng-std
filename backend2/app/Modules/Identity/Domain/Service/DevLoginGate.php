<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Service;

/**
 * THE rule that says whether the password-less dev sign-in exists at all.
 *
 * One pure function, because this is the one place where being wrong is expensive: a dev door that
 * survives into production is an unauthenticated account-takeover endpoint. A rule spread over a
 * route file, a controller and a config read is a rule that can be half-satisfied; a rule that is a
 * function can be table-tested and re-asked at every layer that cares.
 *
 * Two locks, and BOTH must be open:
 *
 *   1. the environment is not `production` — a deploy cannot open this door by setting a variable;
 *   2. an explicit opt-in flag (`DEV_LOGIN_ENABLED`, `config('qa.dev_login')`) — so a laptop that
 *      never asked for it does not carry the door either. It defaults to OFF.
 *
 * The default of the flag is false and the environment check is not overridable on purpose: the
 * failure mode of "QA has to set one variable" is a minute of confusion, and the failure mode of
 * the other direction is every account in the database.
 */
final class DevLoginGate
{
    /** The one environment where this door is refused whatever the flag says. */
    public const FORBIDDEN_ENVIRONMENT = 'production';

    public static function isOpen(string $environment, bool $flagEnabled): bool
    {
        if ($environment === self::FORBIDDEN_ENVIRONMENT) {
            return false;
        }

        return $flagEnabled;
    }
}
