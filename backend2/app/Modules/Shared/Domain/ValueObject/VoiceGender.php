<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/**
 * WHOSE VOICE A LINE IS READ WITH — the pack has a voice of each gender for each role (DAY-UI-3, TTS-2).
 *
 * A scene is two people talking, so it is two voices: the partner's and the learner's, never the same one. The
 * partner's gender comes from the role the lesson imagines (`role_gender`); the learner's from the learner's own
 * profile (наряд FIX-3 §1) — two people of one gender are two voices of that gender.
 */
enum VoiceGender: string
{
    case Female = 'female';
    case Male = 'male';

    /** A value off the wire or out of a stored lesson; anything else is «not said». */
    public static function tryFromAny(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(strtolower(trim($value))) : null;
    }
}
