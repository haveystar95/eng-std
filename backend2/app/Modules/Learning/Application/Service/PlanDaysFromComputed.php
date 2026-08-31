<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\ValueObject\ComputedDay;
use App\Modules\Learning\Domain\ValueObject\ComputedPlan;
use App\Modules\Learning\Domain\ValueObject\PlanDayId;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanSkill;

/**
 * A1's answer, turned into rows.
 *
 * One place, because both doors that produce days — the first outline and every reschedule — must
 * produce the same shape, and the second one is the one that runs when a learner is mid-decision.
 */
final class PlanDaysFromComputed
{
    /** @return list<PlanDay> */
    public function build(PlanId $planId, ComputedPlan $computed): array
    {
        $days = [];
        foreach ($computed->days as $day) {
            $days[] = PlanDay::plan(
                id: PlanDayId::generate(),
                planId: $planId,
                dayIndex: $day->index,
                kind: $day->kind,
                title: $day->title,
                outcomeText: $this->outcomeText($day),
                skills: $this->skills($day),
                roleBrief: $this->roleBrief($day),
                scheduledOn: $day->scheduledOn,
            );
        }

        return $days;
    }

    /** «Ты сможешь: …» as one readable block — the promise, in the learner's own language. */
    private function outcomeText(ComputedDay $day): ?string
    {
        $outcomes = $day->outcomes();

        return $outcomes === [] ? null : implode("\n", $outcomes);
    }

    /** @return list<array<string, mixed>> */
    private function skills(ComputedDay $day): array
    {
        return array_map(
            static fn (PlanSkill $s): array => [
                'outcome' => $s->outcome,
                'est_terms' => $s->estTerms,
                'checkpoint' => $s->checkpoint,
                'scene_index' => $s->sceneIndex,
                'position' => $s->position,
            ],
            $day->skills,
        );
    }

    /**
     * The interlocutor, the checkpoints, the day's scenes and its topics in one blob.
     *
     * The checkpoints live HERE and not only on the role, because the final day has checkpoints
     * and no role at all — every checkpoint of the plan, assembled by the server. One field the
     * conversation can read on either kind of day.
     *
     * `phrase_count` and `word_count` are GONE from this snapshot. They were the day's split
     * between lines and substitutions, frozen at scheduling time from a formula that has since
     * become three numbers; freezing them meant a day generated a week later would be asked for a
     * split nobody could re-derive. The counts are computed where they are used, from the day's
     * budget, which is the only input they ever had.
     *
     * @return array<string, mixed>|null
     */
    private function roleBrief(ComputedDay $day): ?array
    {
        if ($day->role === null && $day->checkpoints === [] && $day->topics === []) {
            return null;
        }

        return [
            'checkpoints' => $day->checkpoints,
            'topics' => $day->topics,
            'term_budget' => $day->termBudget,
            // The day AS P2 READS IT, computed once by the scheduler and stored beside the day it
            // describes. Two short scenes merged into one day are two entries here, and a scene
            // split over two days appears in both with only the abilities that landed there —
            // neither of which the single `role` below can say.
            'scenes' => $day->scenes,
            'role' => $day->role === null ? null : [
                'name' => $day->role->name,
                'opening_lines' => $day->role->openingLines,
                'if_silent' => $day->role->ifSilent,
            ],
        ];
    }
}
