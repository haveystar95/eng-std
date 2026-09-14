<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/**
 * WHOSE VOICE A LINE IS READ WITH — the pack has one voice of each (DAY-UI-3).
 *
 * A scene is two people talking, so it is two voices: the partner's and the learner's, never the
 * same one (owner, DAY-UI-3). The partner's gender comes from the role the lesson imagines
 * (`role_gender`); the learner is the other one.
 */
enum VoiceGender: string
{
    case Female = 'female';
    case Male = 'male';

    public function opposite(): self
    {
        return $this === self::Female ? self::Male : self::Female;
    }

    /** A value off the wire or out of a stored lesson; anything else is «not said». */
    public static function tryFromAny(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(strtolower(trim($value))) : null;
    }
}
