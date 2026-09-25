<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * WHOSE VOICE A NEW SCENE'S PARTNER SPEAKS IN (наряд FIX-4c §1) — the rule, and only the rule.
 *
 * The pack has two partner voices of each gender. A scene is cast ONCE, when its lesson is accepted — the moment its
 * role's gender is first known (it is not known when the plan is built: the gender comes with the lesson; решение
 * приёмки FIX-4c 25.09) — and keeps the voice for good. Scenes of one gender alternate in the plan's order, 1, 2, 1, 2…:
 * a scene takes the OTHER voice of the nearest earlier scene of its gender that has one, and voice 1 when there is none.
 * So the roles Ж, Ж, М, Ж of a plan speak F1, F2, M1, F1, and two neighbouring scenes of one gender never sound like one
 * person — the registrar and the doctor of a rehearsal are two women, and now two voices.
 *
 * A neighbour whose voice the pack no longer names is not voice 1, so the new scene takes voice 1. A pack with one voice
 * of the gender gives it to every scene.
 */
final class PartnerVoiceRota
{
    /**
     * @param  list<array{order: int, gender: VoiceGender|null, voice: string|null}>  $scenes  the plan's other scenes
     * @param  list<string>  $voices  the pack's partner voices of `$gender`, voice 1 first
     */
    public static function pick(int $order, VoiceGender $gender, array $scenes, array $voices): ?string
    {
        if ($voices === []) {
            return null;
        }
        $before = null;
        foreach ($scenes as $scene) {
            if ($scene['gender'] === $gender && $scene['voice'] !== null && $scene['order'] < $order
                && ($before === null || $scene['order'] > $before['order'])) {
                $before = $scene;
            }
        }
        if ($before === null || count($voices) < 2) {
            return $voices[0];
        }

        return $before['voice'] === $voices[0] ? $voices[1] : $voices[0];
    }
}
