<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * A `skill_ref` THE MODEL MISNUMBERED, brought back onto the scene's own ids — when that is
 * unambiguous (вердикт владельца по наряду GEN-1, флап V14).
 *
 * The scene hands the day its skills as `s2.1`, `s2.2`, `s2.3`, and two live day-2 answers came
 * back with `s1`…`s5`, `s2.0` and `s1.4` on every card. The abilities were the right ones — the
 * numbering was the model's own — and refusing the day card by card sent P2R after eight cards it
 * could not fix, then killed the day. The address of a skill is a prefix and an ordinal, and an
 * ordinal survives a wrong prefix: `s1.2` on scene 2 means the second ability of THIS scene.
 *
 * Three readings, in this order, and anything else is null — «не однозначно», and the day is
 * written again whole rather than patched or buried:
 *
 *   the ref already is one of the ids — kept;
 *   the scene has ONE skill — every ref means it;
 *   the ref carries an ordinal (`s1.4` → 4, `s3` → 3, `s2.0` → 0): 1…K maps to the K-th id, and
 *   0 maps to the first (a model counting from zero), because both are one answer; 4 on a scene
 *   of three is nobody's.
 *
 * Pure and in Domain, beside the validator that judges the same field.
 */
final class PlanSkillRefNormalizer
{
    /**
     * @param  list<string>  $skillIds  the scene's ids, as the outline stored them (`s2.1`, …)
     * @return string|null  the id to use, or null when the ref cannot be read unambiguously
     */
    public static function normalize(?string $ref, array $skillIds): ?string
    {
        $ref = mb_strtolower(trim((string) $ref));
        if ($skillIds === []) {
            return $ref === '' ? null : $ref;
        }
        if (in_array($ref, $skillIds, true)) {
            return $ref;
        }

        // The scene's own numbering: one prefix, ordinals in the order the ids were listed.
        $prefix = null;
        $byOrdinal = [];
        foreach ($skillIds as $position => $id) {
            if (preg_match('/^(.*?)(\d+)$/', $id, $m) !== 1) {
                return null;
            }
            $prefix ??= $m[1];
            if ($m[1] !== $prefix) {
                return null;
            }
            $byOrdinal[(int) $m[2]] = $id;
            $byOrdinal[$position + 1] ??= $id;
        }

        if (count($skillIds) === 1) {
            return $skillIds[0];
        }

        if ($ref === '' || preg_match('/(\d+)$/', $ref, $m) !== 1) {
            return null;
        }
        $ordinal = (int) $m[1];
        if ($ordinal === 0) {
            return $skillIds[0];
        }

        return $byOrdinal[$ordinal] ?? null;
    }
}
