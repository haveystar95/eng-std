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

    /**
     * The day's abilities, WITH THE ID EVERY CARD OF THE DAY POINTS AT.
     *
     * `id` is what makes «почему я это учу» mechanical: the day's cards carry it in `skill_ref` and
     * the gate refuses a card that names a skill this scene never promised. It is stored on the
     * snapshot rather than looked up through `plan_skills`, for the reason the snapshot exists at
     * all — the day is generated from ONE row, and a join is a second answer to the question of
     * what this day promises.
     *
     * @return list<array<string, mixed>>
     */
    private function skills(ComputedDay $day): array
    {
        return array_map(
            static fn (PlanSkill $s): array => [
                'id' => $s->id,
                'outcome' => $s->outcome,
                'est_terms' => $s->estTerms,
                'checkpoint' => $s->checkpoint,
                'topics' => $s->topics,
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
     * NO COUNTS AT ALL any more. `phrase_count`/`word_count` went in v0.3 (frozen numbers a day
     * generated a week later could not re-derive), and the three exact counts that replaced them
     * went in v0.4 with the arrays they counted: a day is a SCENE, its shelves have guide sizes the
     * prompt states and the validator counts as warnings, and `term_budget` survives as one number
     * ({@see \App\Modules\Learning\Domain\Service\SceneDay::UNITS}) that nobody is asked to hit.
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
            // THE SCENE, exactly as P2 will be handed it ({@see ComputedDay::sceneJson()}). One
            // scene and no list of them: a day IS a situation since v0.4, so the shape that used to
            // hold «the halves of two scenes that landed here» has nothing left to say.
            'scene' => $day->sceneJson(),
            'intro' => $day->intro,
            'skills' => $this->skills($day),
            'role' => $day->role === null ? null : [
                'name' => $day->role->name,
                'opening_lines' => $day->role->openingLines,
                'if_silent' => $day->role->ifSilent,
            ],
        ];
    }
}
