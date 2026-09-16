<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Service\SpokenLines;

/**
 * THE AUDIO STUB OF A CARD (наряд SESSION-1a, разд. 0): every element that sounds carries `{ref, voice, url,
 * duration_ms}`, `ref` named the way `plan_line_audios` names the file (`x3`, `x3b`, `p2`, `p2.f3`, `v5`).
 *
 * A dealt payload never holds an id or an address — the voice may arrive after the day is dealt — so `url` and
 * `duration_ms` are null here and resolved at read time; this exact four-key shape is what the reader looks for
 * anywhere in a payload.
 */
final class Audio
{
    /** @return array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null} */
    public static function of(string $ref): array
    {
        return ['ref' => $ref, 'voice' => SpokenLines::speakerOf($ref)->value, 'url' => null, 'duration_ms' => null];
    }
}
