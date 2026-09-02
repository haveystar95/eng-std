<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Domain\ValueObject\ComputedPlan;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;
use App\Modules\Learning\Domain\ValueObject\PlanSkillRecord;
use App\Modules\Shared\Domain\ValueObject\Ulid;

/**
 * The plan's abilities as ROWS — P1's answer with the scheduler's verdict written next to it.
 *
 * The mirror of {@see PlanDaysFromComputed}, and the direction the arrow now points: the abilities
 * are the source, the days are what the scheduler makes of them today. Both are written by the same
 * two doors (the first outline and every reschedule), so both are built in one place each, from the
 * same `ComputedPlan`.
 *
 * EVERY ability of the outline gets a row, including the dropped ones. A dropped ability is not
 * absent from the plan — it is the thing the «срок мал» card is about, and a learner who moves the
 * event date gets it back. Deleting it would make «что я потерял, сдвинув дату» unanswerable.
 */
final class PlanSkillsFromComputed
{
    /** @return list<PlanSkillRecord> */
    public function build(PlanOutline $outline, ComputedPlan $computed): array
    {
        // position → the day it landed on. Read off the days rather than recomputed, so the rows
        // and the day snapshots cannot disagree about where an ability went.
        $dayOf = [];
        foreach ($computed->days as $day) {
            foreach ($day->skills as $skill) {
                $dayOf[$skill->position] = $day->index;
            }
        }

        $dropped = [];
        foreach ($computed->dropped as $skill) {
            $dropped[$skill->position] = true;
        }

        $records = [];
        foreach ($outline->scenes as $scene) {
            // Derived from the scene's own lines since v0.4 — P1 no longer answers with a role
            // object, and a name invented here would be a fact about a person nobody described.
            $role = $scene->role();
            foreach ($scene->skills as $skill) {
                $records[] = new PlanSkillRecord(
                    // The ROW's id is a ULID, as every row here has; the skill's own `id` («s1.2»)
                    // is what the day's cards point at and lives in `skill_ref` beside it. Two
                    // different questions — «which row is this» and «which promise is this» — and
                    // collapsing them would make a re-scheduled plan renumber the promises its
                    // already-written days name.
                    id: Ulid::generate(),
                    skillRef: $skill->id,
                    sceneIndex: $scene->index,
                    sceneTitle: $scene->title,
                    role: $role === null ? null : [
                        'name' => $role->name,
                        'opening_lines' => $role->openingLines,
                        'if_silent' => $role->ifSilent,
                    ],
                    skillIndex: $skill->skillIndex,
                    outcome: $skill->outcome,
                    checkpoint: $skill->checkpoint,
                    estTerms: $skill->estTerms,
                    topics: $skill->topics,
                    position: $skill->position,
                    dayIndex: $dayOf[$skill->position] ?? null,
                    dropped: isset($dropped[$skill->position]),
                );
            }
        }

        return $records;
    }
}
